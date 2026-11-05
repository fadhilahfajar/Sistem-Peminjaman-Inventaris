<?php

namespace App\Services;

use App\Models\Division;
use Illuminate\Support\Collection;

/** Kelola divisi master (FR-A-16, BR-27). Tidak ada penghapusan, riwayat tetap utuh. */
class DivisiService
{
    /** Semua baris (aktif dan nonaktif), urut nama. */
    public function daftar(): Collection
    {
        return Division::query()->orderBy('nama')->get();
    }

    /** Tambah divisi baru; aktif secara bawaan (BR-27). */
    public function buat(array $data): Division
    {
        return Division::create([
            'nama' => $data['nama'],
            'aktif' => true,
        ]);
    }

    /** Ubah nama dan atau status aktif. Menonaktifkan selalu boleh (BR-27). */
    public function ubah(Division $divisi, array $data): Division
    {
        $divisi->update($data);

        return $divisi->refresh();
    }
}
