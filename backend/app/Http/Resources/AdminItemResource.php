<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nama' => $this->nama,
            'deskripsi' => $this->deskripsi,
            'kategori' => $this->kategori ? [
                'id' => $this->kategori->id,
                'nama' => $this->kategori->nama,
            ] : null,
            'total' => (int) $this->total,
            'tersedia' => (int) $this->tersedia,
            'rusak' => (int) $this->rusak,
            'sedang_keluar' => max(0, (int) $this->total - (int) $this->tersedia - (int) $this->rusak),
            'kondisi' => $this->kondisi(),
            'boleh_diperpanjang' => (bool) $this->boleh_diperpanjang,
            'dibuat_pada' => $this->dibuat_pada?->toISOString(),
            'diubah_pada' => $this->diubah_pada?->toISOString(),
        ];
    }

    /** BR-29: kondisi diturunkan dari angka stok, bukan kolom. */
    private function kondisi(): string
    {
        $rusak = (int) $this->rusak;
        $total = (int) $this->total;

        if ($rusak === 0) {
            return 'baik';
        }

        if ($total > 0 && $rusak === $total) {
            return 'semua_rusak';
        }

        if ($rusak > 0 && $rusak < $total) {
            return 'sebagian_rusak';
        }

        return 'baik';
    }
}
