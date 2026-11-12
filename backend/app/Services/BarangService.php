<?php

namespace App\Services;

use App\Exceptions\GalatBisnis;
use App\Models\Item;
use App\Support\Audit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class BarangService
{
    public function __construct(private readonly StokService $stokService) {}

    public function daftar(?string $q = null, ?int $kategoriId = null, int $pageSize = 25): LengthAwarePaginator
    {
        $pageSize = max(1, min($pageSize, 100));

        return Item::query()
            ->with('kategori')
            ->when($q !== null && trim($q) !== '', function ($query) use ($q) {
                $escaped = addcslashes(trim($q), '%_\\');
                $query->where('nama', 'like', "%{$escaped}%");
            })
            ->when($kategoriId !== null && $kategoriId > 0, function ($query) use ($kategoriId) {
                $query->where('kategori_id', $kategoriId);
            })
            ->orderBy('nama')
            ->paginate($pageSize);
    }

    public function buat(array $data, int $userId): Item
    {
        return DB::transaction(function () use ($data, $userId) {
            $item = Item::create([
                'nama' => $data['nama'],
                'deskripsi' => $data['deskripsi'] ?? null,
                'kategori_id' => $data['kategori_id'],
                'total' => 0,
                'tersedia' => 0,
                'rusak' => 0,
                'boleh_diperpanjang' => array_key_exists('boleh_diperpanjang', $data)
                    ? (bool) $data['boleh_diperpanjang']
                    : true,
            ]);

            $jumlahAwal = array_key_exists('jumlah_awal', $data) ? (int) $data['jumlah_awal'] : 0;
            if ($jumlahAwal > 0) {
                $this->stokService->tambah($item->id, $jumlahAwal, $userId, 'Stok awal');
            }

            $item->refresh();

            Audit::catat(
                aksi: 'barang.dibuat',
                entitas: 'items',
                entitasId: $item->id,
                userId: $userId,
                detail: [
                    'nama' => $item->nama,
                    'kategori_id' => $item->kategori_id,
                    'boleh_diperpanjang' => (bool) $item->boleh_diperpanjang,
                    'jumlah_awal' => $jumlahAwal,
                ],
            );

            return $item->load('kategori');
        }, 3);
    }

    public function ubah(Item $item, array $data, int $userId): Item
    {
        return DB::transaction(function () use ($item, $data, $userId) {
            $sebelum = [
                'nama' => $item->nama,
                'deskripsi' => $item->deskripsi,
                'kategori_id' => $item->kategori_id,
                'boleh_diperpanjang' => (bool) $item->boleh_diperpanjang,
            ];

            $item->update([
                'nama' => $data['nama'],
                'deskripsi' => $data['deskripsi'] ?? null,
                'kategori_id' => $data['kategori_id'],
                'boleh_diperpanjang' => (bool) $data['boleh_diperpanjang'],
            ]);

            $item->refresh();

            Audit::catat(
                aksi: 'barang.diubah',
                entitas: 'items',
                entitasId: $item->id,
                userId: $userId,
                detail: [
                    'sebelum' => $sebelum,
                    'sesudah' => [
                        'nama' => $item->nama,
                        'deskripsi' => $item->deskripsi,
                        'kategori_id' => $item->kategori_id,
                        'boleh_diperpanjang' => (bool) $item->boleh_diperpanjang,
                    ],
                ],
            );

            return $item->load('kategori');
        }, 3);
    }

    public function hapus(Item $item, int $userId): void
    {
        DB::transaction(function () use ($item, $userId) {
            // Kunci barang agar pemeriksaan BR-14 dan soft-delete atomik terhadap peminjaman baru.
            $terkunci = Item::query()->lockForUpdate()->findOrFail($item->id);
            $terkunci->loadMissing('kategori');

            // BR-14: unit dipinjam ditambah menunggu verifikasi = total - tersedia - rusak.
            $sedangKeluar = max(0, (int) $terkunci->total - (int) $terkunci->tersedia - (int) $terkunci->rusak);
            if ($sedangKeluar > 0) {
                throw GalatBisnis::konflik(
                    "Barang tidak dapat dihapus karena masih ada {$sedangKeluar} unit yang sedang dipinjam atau menunggu verifikasi.",
                    'barang_masih_dipinjam'
                );
            }

            if ($terkunci->rusak > 0) {
                throw GalatBisnis::konflik(
                    "Barang tidak dapat dihapus karena masih ada {$terkunci->rusak} unit rusak. Selesaikan dulu lewat perbaikan atau afkir.",
                    'barang_masih_rusak'
                );
            }

            if (! $terkunci->kategori || ! $terkunci->kategori->aktif) {
                throw GalatBisnis::konflik(
                    'Barang tidak dapat dihapus karena kategorinya dinonaktifkan.',
                    'kategori_nonaktif'
                );
            }

            $terkunci->delete();

            Audit::catat(
                aksi: 'barang.dihapus',
                entitas: 'items',
                entitasId: $terkunci->id,
                userId: $userId,
                detail: [
                    'nama' => $terkunci->nama,
                    'kategori_id' => $terkunci->kategori_id,
                ],
            );
        }, 3);
    }

    public function daftarPerbaikan(): Collection
    {
        return Item::query()
            ->with('kategori')
            ->where('rusak', '>', 0)
            ->orderByDesc('rusak')
            ->orderBy('nama')
            ->get();
    }
}
