<?php

namespace App\Services;

use App\Exceptions\GalatBisnis;
use App\Models\AuditLog;
use App\Models\Item;
use App\Models\ReturnClaim;
use App\Support\BukuBesarStok;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Verifikasi klaim pengembalian oleh admin (DESAIN 7.3, BR-05 sampai BR-08).
 * Satu-satunya tempat klaim mengubah items (BR-03: klaim sendiri tidak mengubah stok).
 */
class VerifikasiService
{
    private const HASIL = ['baik', 'rusak', 'hilang', 'ditolak'];

    /**
     * @param  list<array{line_id: int, qty_baik: int, qty_rusak: int, qty_hilang: int, qty_ditolak: int}>  $lines
     */
    public function proses(int $klaimId, array $lines, ?string $catatanAdmin, int $adminId): ReturnClaim
    {
        $hasil = $this->normalisasi($lines);
        $catatan = $this->catatan($catatanAdmin);

        return DB::transaction(fn () => $this->jalankan($klaimId, $hasil, $catatan, $adminId), 3);
    }

    private function jalankan(int $klaimId, array $hasil, ?string $catatan, int $adminId): ReturnClaim
    {
        // Urutan kunci (AGENTS 5): klaim dulu, lalu items menurut id menaik.
        $klaim = ReturnClaim::query()->whereKey($klaimId)->lockForUpdate()->first();

        if ($klaim === null) {
            throw new GalatBisnis(404, 'klaim_tidak_ditemukan', 'Klaim tidak ditemukan.');
        }

        if ($klaim->status !== 'menunggu') {
            throw GalatBisnis::konflik('Klaim ini sudah diverifikasi.', 'klaim_sudah_diproses');
        }

        $baris = $klaim->lines()->with('loanItem')->get();
        $berbeda = $this->validasiBaris($baris, $hasil);

        if ($berbeda && $catatan === null) {
            throw ValidationException::withMessages([
                'catatan_admin' => ['Catatan admin wajib bila hasil berbeda dari laporan atau ada unit ditolak.'],
            ]);
        }

        $perItem = [];
        foreach ($baris as $b) {
            $itemId = (int) $b->loanItem->item_id;
            $h = $hasil[(int) $b->id];

            foreach (['baik', 'rusak', 'hilang'] as $k) {
                $perItem[$itemId][$k] = ($perItem[$itemId][$k] ?? 0) + $h[$k];
            }
        }
        ksort($perItem);

        foreach ($perItem as $itemId => $t) {
            $item = Item::withTrashed()->whereKey($itemId)->lockForUpdate()->firstOrFail();

            $item->tersedia += $t['baik'];
            $item->rusak += $t['rusak'];
            $item->total -= $t['hilang'];
            $item->save();

            $this->catatMutasi($item, 'VERIFIKASI_BAIK', 0, $t['baik'], 0, $klaim->id, $adminId, $catatan, $t['baik']);
            $this->catatMutasi($item, 'VERIFIKASI_RUSAK', 0, 0, $t['rusak'], $klaim->id, $adminId, $catatan, $t['rusak']);
            $this->catatMutasi($item, 'VERIFIKASI_HILANG', -$t['hilang'], 0, 0, $klaim->id, $adminId, $catatan, $t['hilang']);
        }

        foreach ($baris as $b) {
            $h = $hasil[(int) $b->id];
            $b->update([
                'qty_baik' => $h['baik'],
                'qty_rusak' => $h['rusak'],
                'qty_hilang' => $h['hilang'],
                'qty_ditolak' => $h['ditolak'],
            ]);
        }

        // BR-07: pembaruan bersyarat; tidak ada baris terdampak berarti sudah diproses.
        $terdampak = ReturnClaim::query()->whereKey($klaim->id)->where('status', 'menunggu')->update([
            'status' => 'diverifikasi',
            'diverifikasi_oleh' => $adminId,
            'diverifikasi_pada' => now(),
            'catatan_admin' => $catatan,
        ]);

        if ($terdampak !== 1) {
            throw GalatBisnis::konflik('Klaim ini sudah diverifikasi.', 'klaim_sudah_diproses');
        }

        $this->cekBr01(array_keys($perItem));
        $this->catatAudit($klaim, $hasil, $berbeda, $adminId);

        return $klaim->fresh(['lines']);
    }

    /** Mutasi hanya ditulis bila ada unit pada hasil itu. Satu baris per barang per jenis. */
    private function catatMutasi(Item $item, string $jenis, int $dt, int $ds, int $dr, int $klaimId, int $adminId, ?string $catatan, int $jumlah): void
    {
        if ($jumlah < 1) {
            return;
        }

        BukuBesarStok::catat($item, $jenis, $dt, $ds, $dr, 'return_claims', $klaimId, $adminId, $catatan);
    }

    /** Memeriksa BR-01 per barang yang terdampak. Gagal berarti rollback (DESAIN 7.3 langkah 5). */
    private function cekBr01(array $itemIds): void
    {
        foreach ($itemIds as $id) {
            $i = DB::selectOne('SELECT total, tersedia, rusak FROM items WHERE id = ?', [$id]);

            $pinjam = (int) DB::selectOne(
                'SELECT COALESCE(SUM(li.qty_dipinjam), 0) AS n FROM loan_items li '
                .'JOIN loans l ON l.id = li.loan_id WHERE li.item_id = ? AND l.dibatalkan_pada IS NULL',
                [$id],
            )->n;

            $menunggu = (int) DB::selectOne(
                'SELECT COALESCE(SUM(rcl.qty), 0) AS n FROM return_claim_lines rcl '
                .'JOIN return_claims rc ON rc.id = rcl.claim_id '
                .'JOIN loan_items li ON li.id = rcl.loan_item_id '
                .'JOIN loans l ON l.id = li.loan_id '
                ."WHERE li.item_id = ? AND rc.status = 'menunggu' AND l.dibatalkan_pada IS NULL",
                [$id],
            )->n;

            $terverifikasi = (int) DB::selectOne(
                'SELECT COALESCE(SUM(COALESCE(rcl.qty_baik, 0) + COALESCE(rcl.qty_rusak, 0) + COALESCE(rcl.qty_hilang, 0)), 0) AS n '
                .'FROM return_claim_lines rcl '
                .'JOIN return_claims rc ON rc.id = rcl.claim_id '
                .'JOIN loan_items li ON li.id = rcl.loan_item_id '
                .'JOIN loans l ON l.id = li.loan_id '
                ."WHERE li.item_id = ? AND rc.status = 'diverifikasi' AND l.dibatalkan_pada IS NULL",
                [$id],
            )->n;

            $dipinjam = $pinjam - $menunggu - $terverifikasi;
            $jumlah = (int) $i->tersedia + (int) $i->rusak + $dipinjam + $menunggu;

            if ($dipinjam < 0 || $jumlah !== (int) $i->total) {
                throw new LogicException("BR-01 dilanggar pada barang #{$id} setelah verifikasi: {$jumlah} != {$i->total}.");
            }
        }
    }

    /** Detail audit tidak memuat catatan bebas, hanya ringkasan angka. */
    private function catatAudit(ReturnClaim $klaim, array $hasil, bool $berbeda, int $adminId): void
    {
        $ringkas = ['baik' => 0, 'rusak' => 0, 'hilang' => 0, 'ditolak' => 0];
        foreach ($hasil as $h) {
            foreach (self::HASIL as $k) {
                $ringkas[$k] += $h[$k];
            }
        }

        AuditLog::create([
            'user_id' => $adminId,
            'aksi' => 'verifikasi_klaim',
            'entitas' => 'return_claims',
            'entitas_id' => $klaim->id,
            'detail' => ['kode_klaim' => $klaim->kode_klaim, 'hasil' => $ringkas, 'berbeda_dari_laporan' => $berbeda],
        ]);
    }

    /** @return bool true bila ada hasil yang berbeda dari laporan atau ada unit ditolak (BR-06). */
    private function validasiBaris($baris, array $hasil): bool
    {
        $galat = [];

        $ada = $baris->pluck('id')->map(fn ($v) => (int) $v)->sort()->values()->all();
        $dikirim = collect(array_keys($hasil))->sort()->values()->all();

        if ($ada !== $dikirim) {
            throw ValidationException::withMessages([
                'lines' => ['Hasil harus diisi untuk semua baris klaim, tidak boleh lebih atau kurang.'],
            ]);
        }

        $berbeda = false;
        foreach ($baris as $b) {
            $h = $hasil[(int) $b->id];
            $jumlah = array_sum($h);

            if ($jumlah !== (int) $b->qty) {
                $galat["lines.{$b->id}"][] = "Jumlah baik, rusak, hilang, dan ditolak harus sama dengan {$b->qty}.";

                continue;
            }

            if ($h['ditolak'] > 0 || $h[$b->kondisi_dilaporkan] !== (int) $b->qty) {
                $berbeda = true;
            }
        }

        if ($galat !== []) {
            throw ValidationException::withMessages($galat);
        }

        return $berbeda;
    }

    private function normalisasi(array $lines): array
    {
        $galat = [];
        $hasil = [];

        if ($lines === []) {
            $galat['lines'][] = 'Hasil verifikasi wajib diisi.';
        }

        foreach (array_values($lines) as $i => $l) {
            $l = is_array($l) ? $l : [];
            $id = $this->bulat($l['line_id'] ?? null);

            if ($id === null || $id < 1) {
                $galat["lines.{$i}.line_id"][] = 'Baris klaim tidak valid.';

                continue;
            }

            if (isset($hasil[$id])) {
                $galat["lines.{$i}.line_id"][] = 'Baris klaim tidak boleh muncul dua kali.';

                continue;
            }

            $baris = [];
            foreach (self::HASIL as $k) {
                $n = $this->bulat($l['qty_'.$k] ?? null);

                if ($n === null || $n < 0) {
                    $galat["lines.{$i}.qty_{$k}"][] = 'Jumlah harus bilangan bulat 0 atau lebih.';
                }

                $baris[$k] = $n ?? 0;
            }

            $hasil[$id] = $baris;
        }

        if ($galat !== []) {
            throw ValidationException::withMessages($galat);
        }

        return $hasil;
    }

    private function catatan(?string $nilai): ?string
    {
        $teks = trim((string) $nilai);

        if ($teks === '') {
            return null;
        }

        if (mb_strlen($teks) > 500) {
            throw ValidationException::withMessages(['catatan_admin' => ['Catatan admin maksimal 500 karakter.']]);
        }

        return $teks;
    }

    private function bulat(mixed $v): ?int
    {
        if (is_int($v)) {
            return $v;
        }

        return is_string($v) && preg_match('/^-?[0-9]+$/', $v) === 1 ? (int) $v : null;
    }
}
