<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Division;
use App\Models\Item;
use App\Models\User;
use App\Services\BarangService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeder data contoh untuk pengujian manual kiosk di database dev (bukan produksi).
 * Jalankan eksplisit: php artisan db:seed --class=BarangContohSeeder
 *
 * Stok awal dibuat lewat BarangService -> StokService::tambah sehingga setiap
 * barang memiliki baris TAMBAH_STOK di stock_movements (aturan AGENTS.md no. 4).
 */
class BarangContohSeeder extends Seeder
{
    public function run(): void
    {
        $petugas = User::firstOrCreate(
            ['email' => 'petugas-seeder@example.com'],
            [
                'name' => 'Petugas IT (Seeder)',
                // Password acak, tidak diketahui siapa pun (aturan AGENTS.md no. 12).
                'password' => Str::password(16),
            ],
        );

        $barangService = app(BarangService::class);

        $data = [
            ['nama' => 'Laptop ThinkPad T14', 'kategori' => 'Laptop', 'jumlah' => 5, 'deskripsi' => 'Laptop 14 inch untuk rapat atau pelatihan.', 'perpanjang' => true],
            ['nama' => 'Proyektor Epson EB-X51', 'kategori' => 'Proyektor', 'jumlah' => 3, 'deskripsi' => 'Proyektor XGA 3.600 lumen.', 'perpanjang' => true],
            ['nama' => 'Mikrofon Wireless Shure', 'kategori' => 'Audio', 'jumlah' => 4, 'deskripsi' => 'Set mikrofon wireless ganda.', 'perpanjang' => false],
            ['nama' => 'Kabel HDMI 5 Meter', 'kategori' => 'Aksesoris', 'jumlah' => 10, 'deskripsi' => 'Kabel HDMI v2.0 panjang 5 meter.', 'perpanjang' => false],
            ['nama' => 'Adaptor Laptop 65W', 'kategori' => 'Aksesoris', 'jumlah' => 2, 'deskripsi' => 'Adaptor charger laptop universal 65 watt.', 'perpanjang' => false],
            ['nama' => 'Speaker Bluetooth JBL', 'kategori' => 'Audio', 'jumlah' => 2, 'deskripsi' => 'Speaker bluetooth untuk acara kecil.', 'perpanjang' => true],
        ];

        foreach ($data as $row) {
            if (Item::withTrashed()->where('nama', $row['nama'])->exists()) {
                continue; // idempoten: aman dijalankan berulang
            }

            $kategori = Category::firstOrCreate(['nama' => $row['kategori']], ['aktif' => true]);

            $barangService->buat([
                'nama' => $row['nama'],
                'deskripsi' => $row['deskripsi'],
                'kategori_id' => $kategori->id,
                'boleh_diperpanjang' => $row['perpanjang'],
                'jumlah_awal' => $row['jumlah'],
            ], $petugas->id);
        }

        // Pastikan divisi aktif tersedia untuk uji peminjaman kiosk.
        foreach (['IT', 'Media', 'EO'] as $nama) {
            Division::firstOrCreate(['nama' => $nama], ['aktif' => true]);
        }
    }
}
