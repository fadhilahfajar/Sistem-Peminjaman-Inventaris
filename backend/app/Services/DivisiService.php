<?php

namespace App\Services;

use App\Models\Division;
use App\Support\Audit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Kelola divisi master (FR-A-16, BR-27). Tidak ada penghapusan, riwayat tetap utuh. */
class DivisiService
{
    /** Semua baris (aktif dan nonaktif), urut nama. */
    public function daftar(): Collection
    {
        return Division::query()->orderBy('nama')->get();
    }

    /** Tambah divisi baru; aktif secara bawaan (BR-27). */
    public function buat(array $data, int $userId): Division
    {
        return DB::transaction(function () use ($data, $userId) {
            $divisi = Division::create([
                'nama' => $data['nama'],
                'aktif' => true,
            ]);

            Audit::catat('divisi.dibuat', 'divisions', $divisi->id, $userId, [
                'nama' => $divisi->nama,
                'aktif' => (bool) $divisi->aktif,
            ]);

            return $divisi;
        }, 3);
    }

    /** Ubah nama dan atau status aktif. Menonaktifkan selalu boleh (BR-27). */
    public function ubah(Division $divisi, array $data, int $userId): Division
    {
        return DB::transaction(function () use ($divisi, $data, $userId) {
            $sebelum = ['nama' => $divisi->nama, 'aktif' => (bool) $divisi->aktif];

            $divisi->update($data);
            $divisi->refresh();

            Audit::catat('divisi.diubah', 'divisions', $divisi->id, $userId, [
                'sebelum' => $sebelum,
                'sesudah' => ['nama' => $divisi->nama, 'aktif' => (bool) $divisi->aktif],
            ]);

            return $divisi;
        }, 3);
    }
}
