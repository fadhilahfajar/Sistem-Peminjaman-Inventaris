<?php

namespace App\Services;

use App\Http\Resources\AdminLoanResource;
use App\Models\Item;

/**
 * Baca-saja ringkasan dasbor admin (FR-A-19, A-15).
 */
class RingkasanAdminService
{
    public function __construct(
        private readonly PeminjamanAdminService $peminjaman,
        private readonly KlaimAdminService $klaim,
    ) {}

    /** @return array<string, mixed> */
    public function ringkasan(): array
    {
        $dipinjam = $this->peminjaman->daftar(['status' => 'dipinjam'], 1)->total();
        $terlambat = $this->peminjaman->daftar(['status' => 'terlambat'], 1)->total();
        $menunggu = $this->peminjaman->daftar(['status' => 'menunggu_verifikasi'], 1)->total();
        $selesai = $this->peminjaman->daftar(['status' => 'selesai'], 1)->total();

        $stok = Item::query()->where('rusak', '>', 0)->selectRaw(
            'COUNT(*) AS barang_rusak, COALESCE(SUM(rusak), 0) AS unit_rusak',
        )->first();

        $antreanKlaim = $this->klaim->daftar([], 1)->total();
        $antreanPerbaikan = (int) ($stok?->barang_rusak ?? 0);

        $terlambatList = AdminLoanResource::collection(
            $this->peminjaman->daftar(['status' => 'terlambat'], 10)->items(),
        );

        return [
            'dipinjam' => $dipinjam,
            'terlambat' => $terlambat,
            'menunggu_verifikasi' => $menunggu,
            'selesai' => $selesai,
            'stok_rusak' => (int) ($stok?->unit_rusak ?? 0),
            'antrean_verifikasi' => $antreanKlaim,
            'antrean_perbaikan' => $antreanPerbaikan,
            'peminjaman_terlambat' => $terlambatList->resolve(),
        ];
    }
}
