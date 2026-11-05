<?php

namespace App\Services;

use App\Exceptions\GalatBisnis;
use App\Models\Item;
use App\Models\Loan;
use App\Models\LoanExtension;
use App\Support\Audit;
use App\Support\Tanggal;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PerpanjanganService
{
    public function __construct(
        private PengaturanService $pengaturan,
        private StatusPeminjaman $status,
    ) {}

    public function olehPeminjam(string $kodePinjam, int $tambahanHari, ?string $alasan = null): Loan
    {
        $this->validasiHari($tambahanHari);
        $alasan = $this->normalisasiAlasan($alasan, false);

        return DB::transaction(function () use ($kodePinjam, $tambahanHari, $alasan) {
            $loan = Loan::query()
                ->where('kode_pinjam', $kodePinjam)
                ->lockForUpdate()
                ->firstOrFail();
            $this->muatDataTerkunci($loan);

            $hariIni = Tanggal::hariIni();
            $status = $this->status->dariModel($loan, $hariIni);

            if ($status['status'] === StatusPeminjaman::DIBATALKAN) {
                throw GalatBisnis::konflik('Peminjaman yang dibatalkan tidak dapat diperpanjang.', 'peminjaman_dibatalkan');
            }

            if ($loan->tanggal_pinjam->format('Y-m-d') > $hariIni) {
                throw GalatBisnis::konflik('Pemesanan belum dimulai dan tidak dapat diperpanjang.', 'pemesanan_tidak_dapat_diperpanjang');
            }

            if ($status['o'] < 1) {
                throw GalatBisnis::konflik('Tidak ada unit yang masih dipinjam untuk diperpanjang.', 'tidak_ada_sisa_peminjaman');
            }

            if ($loan->batas_kembali->format('Y-m-d') < $hariIni) {
                throw GalatBisnis::konflik('Peminjaman yang terlambat tidak dapat diperpanjang oleh peminjam.', 'peminjaman_terlambat');
            }

            if ($loan->extensions->count() >= $this->pengaturan->angka('maks_perpanjangan')) {
                throw GalatBisnis::konflik('Kuota perpanjangan peminjam sudah habis.', 'kuota_perpanjangan_habis');
            }

            $this->validasiBarangBolehDiperpanjang($loan);

            return $this->terapkan($loan, $tambahanHari, $alasan, null, 'peminjam');
        }, 3);
    }

    public function olehAdmin(int $loanId, int $tambahanHari, int $userId, string $alasan): Loan
    {
        $this->validasiHari($tambahanHari);
        $alasan = $this->normalisasiAlasan($alasan, true);

        return DB::transaction(function () use ($loanId, $tambahanHari, $userId, $alasan) {
            $loan = Loan::query()->whereKey($loanId)->lockForUpdate()->firstOrFail();
            $this->muatDataTerkunci($loan);

            if ($loan->dibatalkan_pada !== null) {
                throw GalatBisnis::konflik('Peminjaman yang dibatalkan tidak dapat diperpanjang.', 'peminjaman_dibatalkan');
            }

            if ($loan->tanggal_pinjam->format('Y-m-d') > Tanggal::hariIni()) {
                throw GalatBisnis::konflik('Pemesanan belum dimulai dan tidak dapat diperpanjang.', 'pemesanan_tidak_dapat_diperpanjang');
            }

            if ($this->status->dariModel($loan, Tanggal::hariIni())['o'] < 1) {
                throw GalatBisnis::konflik('Tidak ada unit yang masih dipinjam untuk diperpanjang.', 'tidak_ada_sisa_peminjaman');
            }

            return $this->terapkan($loan, $tambahanHari, $alasan, $userId, 'admin');
        }, 3);
    }

    private function validasiHari(int $tambahanHari): void
    {
        $maksimum = $this->pengaturan->angka('maks_hari_per_perpanjangan');

        if ($tambahanHari < 1 || $tambahanHari > $maksimum) {
            throw ValidationException::withMessages([
                'tambahan_hari' => ["Tambahan hari harus antara 1 dan {$maksimum} hari."],
            ]);
        }
    }

    private function normalisasiAlasan(?string $alasan, bool $wajib): ?string
    {
        $alasan = trim((string) $alasan);

        if ($wajib && $alasan === '') {
            throw ValidationException::withMessages(['alasan' => ['Alasan wajib diisi.']]);
        }

        if (mb_strlen($alasan) > 500) {
            throw ValidationException::withMessages(['alasan' => ['Alasan maksimal 500 karakter.']]);
        }

        return $alasan === '' ? null : $alasan;
    }

    private function muatDataTerkunci(Loan $loan): void
    {
        $loanItems = $loan->items()->orderBy('item_id')->get();
        $itemIds = $loanItems->pluck('item_id')->unique()->sort()->values();
        $items = Item::withTrashed()
            ->whereIn('id', $itemIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($loanItems as $loanItem) {
            $loanItem->setRelation('item', $items->get($loanItem->item_id));
        }

        $loan->setRelation('items', $loanItems);
        $loan->loadMissing('items.claimLines.claim', 'claims', 'extensions');
    }

    private function validasiBarangBolehDiperpanjang(Loan $loan): void
    {
        foreach ($loan->items as $loanItem) {
            $menunggu = 0;
            $terverifikasi = 0;

            foreach ($loanItem->claimLines as $baris) {
                if ($baris->claim->status === 'menunggu') {
                    $menunggu += $baris->qty;
                } elseif ($baris->claim->status === 'diverifikasi') {
                    $terverifikasi += (int) $baris->qty_baik + (int) $baris->qty_rusak + (int) $baris->qty_hilang;
                }
            }

            $sisa = $loanItem->qty_dipinjam - $menunggu - $terverifikasi;

            if ($sisa > 0 && ! $loanItem->item?->boleh_diperpanjang) {
                $nama = $loanItem->item?->nama ?? 'barang';

                throw GalatBisnis::konflik(
                    "Barang {$nama} tidak dapat diperpanjang.",
                    'barang_tidak_dapat_diperpanjang',
                );
            }
        }
    }

    private function terapkan(
        Loan $loan,
        int $tambahanHari,
        ?string $alasan,
        ?int $userId,
        string $diajukanOleh,
    ): Loan {
        $batasLama = $loan->batas_kembali->format('Y-m-d');
        $batasBaru = (new DateTimeImmutable($batasLama, new DateTimeZone('Asia/Jakarta')))
            ->modify("+{$tambahanHari} days")
            ->format('Y-m-d');

        LoanExtension::create([
            'loan_id' => $loan->id,
            'batas_lama' => $batasLama,
            'batas_baru' => $batasBaru,
            'tambahan_hari' => $tambahanHari,
            'alasan' => $alasan,
            'diajukan_oleh' => $diajukanOleh,
            'user_id' => $userId,
        ]);

        $loan->update(['batas_kembali' => $batasBaru]);

        Audit::catat(
            aksi: 'perpanjang_peminjaman',
            entitas: 'loans',
            entitasId: $loan->id,
            userId: $userId,
            detail: [
                'batas_lama' => $batasLama,
                'batas_baru' => $batasBaru,
                'tambahan_hari' => $tambahanHari,
                'diajukan_oleh' => $diajukanOleh,
                'alasan' => $alasan,
            ],
        );

        return $loan->refresh();
    }
}
