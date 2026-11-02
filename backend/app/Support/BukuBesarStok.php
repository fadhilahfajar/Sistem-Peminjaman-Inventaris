<?php

namespace App\Support;

use App\Models\Item;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

class BukuBesarStok
{
    public static function catat(
        Item $item,
        string $jenis,
        int $deltaTotal,
        int $deltaTersedia,
        int $deltaRusak,
        ?string $refTipe = null,
        ?int $refId = null,
        ?int $userId = null,
        ?string $catatan = null,
    ): StockMovement {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Buku besar stok hanya boleh ditulis di dalam transaksi database.');
        }

        if (! self::sesuaiTabelEfek($jenis, $deltaTotal, $deltaTersedia, $deltaRusak)) {
            throw new InvalidArgumentException("Delta tidak sesuai tabel efek untuk jenis {$jenis}.");
        }

        if ($refTipe !== null && mb_strlen($refTipe) > 30) {
            throw new InvalidArgumentException('ref_tipe maksimal 30 karakter.');
        }

        if ($catatan !== null && mb_strlen($catatan) > 500) {
            throw new InvalidArgumentException('Catatan maksimal 500 karakter.');
        }

        return StockMovement::create([
            'item_id' => $item->id,
            'jenis' => $jenis,
            'delta_total' => $deltaTotal,
            'delta_tersedia' => $deltaTersedia,
            'delta_rusak' => $deltaRusak,
            'ref_tipe' => $refTipe,
            'ref_id' => $refId,
            'user_id' => $userId,
            'catatan' => $catatan,
        ]);
    }

    private static function sesuaiTabelEfek(string $jenis, int $dt, int $ds, int $dr): bool
    {
        return match ($jenis) {
            'TAMBAH_STOK' => $dt > 0 && $ds === $dt && $dr === 0,
            'KURANG_STOK' => $dt < 0 && $ds === $dt && $dr === 0,
            'PINJAM' => $dt === 0 && $ds < 0 && $dr === 0,
            'TANDAI_RUSAK' => $dt === 0 && $ds < 0 && $dr === -$ds,
            'KOREKSI_PINJAM' => $dt === 0 && $ds !== 0 && $dr === 0,
            'BATAL_PINJAM', 'VERIFIKASI_BAIK' => $dt === 0 && $ds > 0 && $dr === 0,
            'VERIFIKASI_RUSAK' => $dt === 0 && $ds === 0 && $dr > 0,
            'VERIFIKASI_HILANG' => $dt < 0 && $ds === 0 && $dr === 0,
            'PERBAIKAN_SELESAI' => $dt === 0 && $ds > 0 && $dr === -$ds,
            'AFKIR' => $dt < 0 && $ds === 0 && $dr === $dt,
            default => throw new InvalidArgumentException("Jenis mutasi '{$jenis}' tidak dikenal."),
        };
    }
}
