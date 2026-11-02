<?php

namespace App\Services;

use App\Models\Item;
use App\Support\Audit;
use App\Support\BukuBesarStok;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StokService
{
    public function tambah(int $itemId, int $jumlah, int $userId, string $alasan): Item
    {
        $alasan = $this->validasiMasukan($jumlah, $alasan);

        return $this->terapkan($itemId, 'TAMBAH_STOK', 'tambah_stok', $jumlah, $jumlah, 0, $userId, $alasan);
    }

    public function kurangi(int $itemId, int $jumlah, int $userId, string $alasan): Item
    {
        $alasan = $this->validasiMasukan($jumlah, $alasan);

        return $this->terapkan($itemId, 'KURANG_STOK', 'kurangi_stok', -$jumlah, -$jumlah, 0, $userId, $alasan);
    }

    private function validasiMasukan(int $jumlah, string $alasan): string
    {
        $alasan = trim($alasan);
        $galat = [];

        if ($jumlah < 1) {
            $galat['jumlah'] = 'Jumlah harus bilangan bulat positif.';
        }

        if ($alasan === '') {
            $galat['alasan'] = 'Alasan wajib diisi.';
        } elseif (mb_strlen($alasan) > 500) {
            $galat['alasan'] = 'Alasan maksimal 500 karakter.';
        }

        if ($galat !== []) {
            throw ValidationException::withMessages($galat);
        }

        return $alasan;
    }

    private function terapkan(
        int $itemId,
        string $jenis,
        string $aksi,
        int $deltaTotal,
        int $deltaTersedia,
        int $deltaRusak,
        int $userId,
        string $alasan,
    ): Item {
        return DB::transaction(function () use ($itemId, $jenis, $aksi, $deltaTotal, $deltaTersedia, $deltaRusak, $userId, $alasan) {
            $item = Item::query()->lockForUpdate()->findOrFail($itemId);

            $sebelum = ['total' => $item->total, 'tersedia' => $item->tersedia, 'rusak' => $item->rusak];
            $total = $item->total + $deltaTotal;
            $tersedia = $item->tersedia + $deltaTersedia;
            $rusak = $item->rusak + $deltaRusak;

            if ($tersedia < 0) {
                throw ValidationException::withMessages(['jumlah' => 'Jumlah melebihi stok tersedia.']);
            }

            if ($rusak < 0) {
                throw ValidationException::withMessages(['jumlah' => 'Jumlah melebihi stok rusak.']);
            }

            if ($total < 0 || $tersedia + $rusak > $total) {
                throw ValidationException::withMessages(['jumlah' => 'Perubahan ini melanggar keseimbangan stok barang.']);
            }

            $item->update(['total' => $total, 'tersedia' => $tersedia, 'rusak' => $rusak]);

            BukuBesarStok::catat(
                item: $item,
                jenis: $jenis,
                deltaTotal: $deltaTotal,
                deltaTersedia: $deltaTersedia,
                deltaRusak: $deltaRusak,
                refTipe: 'items',
                refId: $item->id,
                userId: $userId,
                catatan: $alasan,
            );

            Audit::catat($aksi, 'items', $item->id, $userId, [
                'sebelum' => $sebelum,
                'sesudah' => ['total' => $total, 'tersedia' => $tersedia, 'rusak' => $rusak],
                'alasan' => $alasan,
            ]);

            return $item->refresh();
        }, 3);
    }
}
