<?php

namespace App\Services;

use App\Exceptions\GalatBisnis;
use App\Models\Category;
use App\Models\Item;
use App\Support\Audit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Kelola kategori master (FR-A-18, BR-28, BR-33). Tidak ada penghapusan. */
class KategoriService
{
    /** Semua baris (aktif dan nonaktif), urut nama. */
    public function daftar(): Collection
    {
        return Category::query()->orderBy('nama')->get();
    }

    /** Tambah kategori baru; aktif secara bawaan (BR-28). */
    public function buat(array $data, int $userId): Category
    {
        return DB::transaction(function () use ($data, $userId) {
            $kategori = Category::create([
                'nama' => $data['nama'],
                'aktif' => true,
            ]);

            Audit::catat('kategori.dibuat', 'categories', $kategori->id, $userId, [
                'nama' => $kategori->nama,
                'aktif' => (bool) $kategori->aktif,
            ]);

            return $kategori;
        }, 3);
    }

    /** Ubah nama dan atau status aktif. Menonaktifkan ditolak bila masih ada barang aktif (BR-33). */
    public function ubah(Category $kategori, array $data, int $userId): Category
    {
        return DB::transaction(function () use ($kategori, $data, $userId) {
            if (array_key_exists('aktif', $data) && $data['aktif'] === false) {
                $jumlah = Item::query()->where('kategori_id', $kategori->id)->count();

                if ($jumlah > 0) {
                    throw GalatBisnis::konflik(
                        "Kategori ini masih dipakai oleh {$jumlah} barang aktif. Pindahkan barang ke kategori lain dulu.",
                        'kategori_masih_dipakai',
                    );
                }
            }

            $sebelum = ['nama' => $kategori->nama, 'aktif' => (bool) $kategori->aktif];

            $kategori->update($data);
            $kategori->refresh();

            Audit::catat('kategori.diubah', 'categories', $kategori->id, $userId, [
                'sebelum' => $sebelum,
                'sesudah' => ['nama' => $kategori->nama, 'aktif' => (bool) $kategori->aktif],
            ]);

            return $kategori;
        }, 3);
    }
}
