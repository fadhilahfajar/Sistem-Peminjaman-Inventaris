<?php

namespace App\Services;

use App\Exceptions\GalatBisnis;
use App\Models\Item;
use App\Models\Loan;
use App\Support\Audit;
use App\Support\BukuBesarStok;
use App\Support\Tanggal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PembatalanService
{
    public function olehPeminjam(string $kodePinjam): Loan
    {
        return DB::transaction(function () use ($kodePinjam) {
            $loan = Loan::query()
                ->where('kode_pinjam', $kodePinjam)
                ->lockForUpdate()
                ->firstOrFail();

            if ($loan->tanggal_pinjam->format('Y-m-d') <= Tanggal::hariIni()) {
                throw GalatBisnis::konflik(
                    'Pemesanan hanya dapat dibatalkan sebelum tanggal pinjam.',
                    'pembatalan_tidak_diizinkan',
                );
            }

            return $this->batalkan($loan, null, null, 'peminjam');
        }, 3);
    }

    public function olehAdmin(int $loanId, int $userId, string $alasan): Loan
    {
        $alasan = trim($alasan);

        if ($alasan === '') {
            throw ValidationException::withMessages(['alasan' => ['Alasan wajib diisi.']]);
        }

        if (mb_strlen($alasan) > 500) {
            throw ValidationException::withMessages(['alasan' => ['Alasan maksimal 500 karakter.']]);
        }

        return DB::transaction(function () use ($loanId, $userId, $alasan) {
            $loan = Loan::query()->whereKey($loanId)->lockForUpdate()->firstOrFail();

            if ($loan->claims()->exists()) {
                throw GalatBisnis::konflik(
                    'Peminjaman yang sudah memiliki klaim tidak dapat dibatalkan.',
                    'peminjaman_memiliki_klaim',
                );
            }

            return $this->batalkan($loan, $alasan, $userId, 'admin');
        }, 3);
    }

    private function batalkan(Loan $loan, ?string $alasan, ?int $userId, string $pelaku): Loan
    {
        if ($loan->dibatalkan_pada !== null) {
            throw GalatBisnis::konflik('Peminjaman ini sudah dibatalkan.', 'peminjaman_sudah_dibatalkan');
        }

        $loanItems = $loan->items()->orderBy('item_id')->get();
        $dibatalkanPada = now('UTC');

        $terubah = Loan::query()
            ->whereKey($loan->id)
            ->whereNull('dibatalkan_pada')
            ->update([
                'dibatalkan_pada' => $dibatalkanPada,
                'alasan_batal' => $alasan,
                'dibatalkan_oleh' => $userId,
            ]);

        if ($terubah !== 1) {
            throw GalatBisnis::konflik('Peminjaman ini sudah dibatalkan.', 'peminjaman_sudah_dibatalkan');
        }

        foreach ($loanItems as $loanItem) {
            $item = Item::withTrashed()->whereKey($loanItem->item_id)->lockForUpdate()->firstOrFail();
            $tersediaBaru = $item->tersedia + $loanItem->qty_dipinjam;
            $item->update(['tersedia' => $tersediaBaru]);

            BukuBesarStok::catat(
                item: $item,
                jenis: 'BATAL_PINJAM',
                deltaTotal: 0,
                deltaTersedia: $loanItem->qty_dipinjam,
                deltaRusak: 0,
                refTipe: 'loans',
                refId: $loan->id,
                userId: $userId,
                catatan: $alasan,
            );
        }

        Audit::catat(
            aksi: 'batalkan_peminjaman',
            entitas: 'loans',
            entitasId: $loan->id,
            userId: $userId,
            detail: ['pelaku' => $pelaku, 'alasan' => $alasan],
        );

        return $loan->refresh();
    }
}
