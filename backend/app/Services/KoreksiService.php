<?php

namespace App\Services;

use App\Exceptions\GalatBisnis;
use App\Models\Division;
use App\Models\Item;
use App\Models\Loan;
use App\Models\LoanCorrection;
use App\Support\Audit;
use App\Support\BukuBesarStok;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KoreksiService
{
    /**
     * Koreksi peminjaman oleh admin (BR-16).
     *
     * @param  array{
     *     nama_peminjam?: string,
     *     division_id?: int,
     *     telepon?: ?string,
     *     tanggal_pinjam?: string,
     *     batas_kembali?: string,
     *     items?: list<array{item_id: int, qty: int}>,
     *     alasan?: string
     * }  $data
     */
    public function koreksi(int $loanId, int $userId, array $data, ?string $alasan = null): Loan
    {
        $alasanFinal = $this->validasiAlasan($alasan ?? $data['alasan'] ?? null);

        return DB::transaction(function () use ($loanId, $userId, $data, $alasanFinal) {
            $loan = Loan::query()->whereKey($loanId)->lockForUpdate()->firstOrFail();

            if ($loan->dibatalkan_pada !== null) {
                throw GalatBisnis::konflik(
                    'Peminjaman yang dibatalkan tidak dapat dikoreksi.',
                    'peminjaman_dibatalkan',
                );
            }

            $perubahanTercatat = [];

            // 1. Koreksi header peminjaman
            $this->koreksiHeader($loan, $data, $userId, $alasanFinal, $perubahanTercatat);

            // 2. Koreksi jumlah item (BR-16)
            if (isset($data['items']) && is_array($data['items'])) {
                $this->koreksiItems($loan, $data['items'], $userId, $alasanFinal, $perubahanTercatat);
            }

            if (empty($perubahanTercatat)) {
                throw ValidationException::withMessages([
                    'perubahan' => ['Tidak ada perubahan data yang dilakukan.'],
                ]);
            }

            Audit::catat(
                aksi: 'koreksi_peminjaman',
                entitas: 'loans',
                entitasId: $loan->id,
                userId: $userId,
                detail: [
                    'alasan' => $alasanFinal,
                    'perubahan' => $perubahanTercatat,
                ],
            );

            return $loan->fresh(['items', 'corrections']);
        }, 3);
    }

    private function validasiAlasan(?string $alasan): string
    {
        $alasan = trim((string) $alasan);

        if ($alasan === '') {
            throw ValidationException::withMessages(['alasan' => ['Alasan wajib diisi.']]);
        }

        if (mb_strlen($alasan) > 500) {
            throw ValidationException::withMessages(['alasan' => ['Alasan maksimal 500 karakter.']]);
        }

        return $alasan;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{kolom: string, lama: ?string, baru: ?string}>  $perubahan
     */
    private function koreksiHeader(Loan $loan, array $data, int $userId, string $alasan, array &$perubahan): void
    {
        $atributUpdate = [];

        // Nama peminjam
        if (array_key_exists('nama_peminjam', $data)) {
            $namaBaru = trim((string) $data['nama_peminjam']);
            if (mb_strlen($namaBaru) < 2 || mb_strlen($namaBaru) > 100) {
                throw ValidationException::withMessages([
                    'nama_peminjam' => ['Nama peminjam harus antara 2 dan 100 karakter.'],
                ]);
            }

            if ($namaBaru !== $loan->nama_peminjam) {
                $this->catatKoreksi($loan->id, 'nama_peminjam', $loan->nama_peminjam, $namaBaru, $alasan, $userId);
                $perubahan[] = ['kolom' => 'nama_peminjam', 'lama' => $loan->nama_peminjam, 'baru' => $namaBaru];
                $atributUpdate['nama_peminjam'] = $namaBaru;
            }
        }

        // Divisi
        if (array_key_exists('division_id', $data)) {
            $divisiBaru = (int) $data['division_id'];
            if (! Division::whereKey($divisiBaru)->exists()) {
                throw ValidationException::withMessages([
                    'division_id' => ['Divisi tidak valid.'],
                ]);
            }

            if ($divisiBaru !== (int) $loan->division_id) {
                $this->catatKoreksi($loan->id, 'division_id', (string) $loan->division_id, (string) $divisiBaru, $alasan, $userId);
                $perubahan[] = ['kolom' => 'division_id', 'lama' => (string) $loan->division_id, 'baru' => (string) $divisiBaru];
                $atributUpdate['division_id'] = $divisiBaru;
            }
        }

        // Telepon
        if (array_key_exists('telepon', $data)) {
            $teleponBaru = $data['telepon'] !== null ? trim((string) $data['telepon']) : null;
            if ($teleponBaru === '') {
                $teleponBaru = null;
            }

            if ($teleponBaru !== null && ! preg_match('/^[0-9+ ]{8,20}$/', $teleponBaru)) {
                throw ValidationException::withMessages([
                    'telepon' => ['Nomor telepon harus antara 8 dan 20 karakter angka.'],
                ]);
            }

            if ($teleponBaru !== $loan->telepon) {
                $this->catatKoreksi($loan->id, 'telepon', $loan->telepon, $teleponBaru, $alasan, $userId);
                $perubahan[] = ['kolom' => 'telepon', 'lama' => $loan->telepon, 'baru' => $teleponBaru];
                $atributUpdate['telepon'] = $teleponBaru;
            }
        }

        // Tanggal pinjam dan batas kembali
        $tglPinjam = array_key_exists('tanggal_pinjam', $data)
            ? (string) $data['tanggal_pinjam']
            : $loan->tanggal_pinjam->format('Y-m-d');

        $batasKembali = array_key_exists('batas_kembali', $data)
            ? (string) $data['batas_kembali']
            : $loan->batas_kembali->format('Y-m-d');

        if ($batasKembali < $tglPinjam) {
            throw ValidationException::withMessages([
                'batas_kembali' => ['Batas kembali tidak boleh sebelum tanggal pinjam.'],
            ]);
        }

        if (array_key_exists('tanggal_pinjam', $data)) {
            $tglLama = $loan->tanggal_pinjam->format('Y-m-d');
            if ($tglPinjam !== $tglLama) {
                $this->catatKoreksi($loan->id, 'tanggal_pinjam', $tglLama, $tglPinjam, $alasan, $userId);
                $perubahan[] = ['kolom' => 'tanggal_pinjam', 'lama' => $tglLama, 'baru' => $tglPinjam];
                $atributUpdate['tanggal_pinjam'] = $tglPinjam;
            }
        }

        if (array_key_exists('batas_kembali', $data)) {
            $batasLama = $loan->batas_kembali->format('Y-m-d');
            if ($batasKembali !== $batasLama) {
                $this->catatKoreksi($loan->id, 'batas_kembali', $batasLama, $batasKembali, $alasan, $userId);
                $perubahan[] = ['kolom' => 'batas_kembali', 'lama' => $batasLama, 'baru' => $batasKembali];
                $atributUpdate['batas_kembali'] = $batasKembali;
            }
        }

        if (! empty($atributUpdate)) {
            $loan->update($atributUpdate);
        }
    }

    /**
     * @param  list<array{item_id: int, qty: int}>  $itemsInput
     * @param  list<array{kolom: string, lama: ?string, baru: ?string}>  $perubahan
     */
    private function koreksiItems(Loan $loan, array $itemsInput, int $userId, string $alasan, array &$perubahan): void
    {
        $loanItems = $loan->items()->get()->keyBy('item_id');

        // Validasi input dan cek klaim terlebih dahulu
        $perubahanItem = [];
        foreach ($itemsInput as $input) {
            if (! isset($input['item_id']) || ! isset($input['qty'])) {
                throw ValidationException::withMessages([
                    'items' => ['Format item tidak valid.'],
                ]);
            }

            $itemId = (int) $input['item_id'];
            $qtyBaru = (int) $input['qty'];

            if ($qtyBaru < 1) {
                throw ValidationException::withMessages([
                    'items' => ['Jumlah pinjam minimal 1.'],
                ]);
            }

            $loanItem = $loanItems->get($itemId);
            if (! $loanItem) {
                throw ValidationException::withMessages([
                    'items' => ["Barang #{$itemId} tidak ada dalam peminjaman ini."],
                ]);
            }

            if ($qtyBaru === $loanItem->qty_dipinjam) {
                continue;
            }

            // BR-16: Jumlah item hanya dapat dikoreksi bila belum ada klaim pada barang itu
            if ($loanItem->claimLines()->exists()) {
                $item = Item::withTrashed()->find($itemId);
                $nama = $item?->nama ?? "Barang #{$itemId}";

                throw GalatBisnis::konflik(
                    "Barang {$nama} sudah memiliki klaim dan tidak dapat dikoreksi jumlahnya.",
                    'barang_sudah_memiliki_klaim',
                );
            }

            $perubahanItem[$itemId] = [
                'loan_item' => $loanItem,
                'qty_lama' => $loanItem->qty_dipinjam,
                'qty_baru' => $qtyBaru,
            ];
        }

        if (empty($perubahanItem)) {
            return;
        }

        // Urutan kunci: items menurut id menaik (AGENTS.md aturan 5)
        ksort($perubahanItem);

        foreach ($perubahanItem as $itemId => $info) {
            $loanItem = $info['loan_item'];
            $qtyLama = $info['qty_lama'];
            $qtyBaru = $info['qty_baru'];
            $delta = $qtyBaru - $qtyLama; // Δ = qty_baru - qty_lama

            $item = Item::withTrashed()->whereKey($itemId)->lockForUpdate()->firstOrFail();

            // Bila Δ > 0, syarat tersedia ≥ Δ
            if ($delta > 0 && $item->tersedia < $delta) {
                throw GalatBisnis::konflik(
                    "Stok barang {$item->nama} tidak mencukupi untuk penambahan jumlah.",
                    'stok_tidak_cukup',
                );
            }

            // tersedia -= Δ (bila delta < 0, bertambah; bila delta > 0, berkurang)
            $item->update(['tersedia' => $item->tersedia - $delta]);
            $loanItem->update(['qty_dipinjam' => $qtyBaru]);

            // Mutasi stok KOREKSI_PINJAM
            BukuBesarStok::catat(
                item: $item,
                jenis: 'KOREKSI_PINJAM',
                deltaTotal: 0,
                deltaTersedia: -$delta,
                deltaRusak: 0,
                refTipe: 'loans',
                refId: $loan->id,
                userId: $userId,
                catatan: $alasan,
            );

            $kolom = "qty_dipinjam:{$itemId}";
            $this->catatKoreksi($loan->id, $kolom, (string) $qtyLama, (string) $qtyBaru, $alasan, $userId);
            $perubahan[] = ['kolom' => $kolom, 'lama' => (string) $qtyLama, 'baru' => (string) $qtyBaru];
        }
    }

    private function catatKoreksi(int $loanId, string $kolom, ?string $lama, ?string $baru, string $alasan, int $userId): void
    {
        LoanCorrection::create([
            'loan_id' => $loanId,
            'kolom' => $kolom,
            'nilai_lama' => $lama,
            'nilai_baru' => $baru,
            'alasan' => $alasan,
            'user_id' => $userId,
        ]);
    }
}
