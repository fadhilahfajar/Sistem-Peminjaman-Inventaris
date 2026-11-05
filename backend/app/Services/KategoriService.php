<?php

namespace App\Services;

use App\Exceptions\GalatBisnis;
use App\Models\Category;
use App\Models\Item;
use Illuminate\Support\Collection;

/** Kelola kategori master (FR-A-18, BR-28, BR-33). Tidak ada penghapusan. */
class KategoriService
{
    /** Semua baris (aktif dan nonaktif), urut nama. */
    public function daftar(): Collection
    {
        return Category::query()->orderBy('nama')->get();
    }

    /** Tambah kategori baru; aktif secara bawaan (BR-28). */
    public function buat(array $data): Category
    {
        return Category::create([
            'nama' => $data['nama'],
            'aktif' => true,
        ]);
    }

    /** Ubah nama dan atau status aktif. Menonaktifkan ditolak bila masih ada barang aktif (BR-33). */
    public function ubah(Category $kategori, array $data): Category
    {
        if (array_key_exists('aktif', $data) && $data['aktif'] === false) {
            $jumlah = Item::query()->where('kategori_id', $kategori->id)->count();

            if ($jumlah > 0) {
                throw GalatBisnis::konflik(
                    "Kategori ini masih dipakai oleh {$jumlah} barang aktif. Pindahkan barang ke kategori lain dulu.",
                    'kategori_masih_dipakai',
                );
            }
        }

        $kategori->update($data);

        return $kategori->refresh();
    }
}
