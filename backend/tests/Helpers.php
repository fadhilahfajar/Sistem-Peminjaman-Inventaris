<?php

use App\Models\Category;
use App\Models\Division;
use App\Models\Item;
use App\Models\Loan;
use App\Models\User;
use App\Services\BarangService;
use App\Services\KlaimService;
use App\Services\PembatalanService;
use App\Services\PeminjamanService;
use App\Services\VerifikasiService;
use App\Support\Tanggal;
use Illuminate\Support\Str;

/** Buat akun admin uji (role web) dengan email unik. */
function adminUji(bool $aktif = true): User
{
    return User::create([
        'name' => 'Admin',
        'email' => uniqid().'@uji.test',
        'password' => 'rahasia-panjang-123',
        'aktif' => $aktif,
    ]);
}

/** Ambil semua kunci (rekursif) dari struktur JSON/array. */
function kunciJsonRekursif(array $data): array
{
    $hasil = [];
    foreach ($data as $kunci => $nilai) {
        if (is_string($kunci)) {
            $hasil[] = $kunci;
        }
        if (is_array($nilai)) {
            array_push($hasil, ...kunciJsonRekursif($nilai));
        }
    }

    return $hasil;
}

/** Klien SPA: header Origin/Referer frontend dev + sesi kosong. */
function spa()
{
    return test()->withHeaders([
        'Origin' => 'http://localhost:5173',
        'Referer' => 'http://localhost:5173/',
    ])
        ->withSession([]);
}

/** Data statis untuk smoke test templat PDF laporan. */
function dataUjiPdfDasar(): array
{
    return [
        'judul' => 'Laporan Uji Cetak',
        'periode' => '2026-11-01 s.d. 2026-11-17',
        'waktuCetak' => (new DateTimeImmutable('2026-11-17 08:30:00', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d H:i:s'),
        'baris' => [
            ['nama' => 'Laptop é', 'catatan' => 'Amat & aman', 'jumlah' => 12],
            ['nama' => 'Proyektor', 'catatan' => "<script>alert('x')</script> > 1", 'jumlah' => 3],
        ],
    ];
}

/** Siapkan satu data peminjaman untuk tiap status (uji laporan, T-41). */
function siapkanDataLaporan(User $admin): array
{
    $tanggal = fn (int $offset): string => (new DateTimeImmutable(
        Tanggal::hariIni(),
        new DateTimeZone('Asia/Jakarta'),
    ))->modify("{$offset} days")->format('Y-m-d');

    $kategoriA = Category::factory()->create(['nama' => 'Komputer']);
    $kategoriB = Category::factory()->create(['nama' => 'Audio']);
    $divisiIT = Division::firstOrCreate(['nama' => 'IT'], ['aktif' => true]);
    $divisiLain = Division::firstOrCreate(['nama' => 'Media'], ['aktif' => true]);

    $itemA = app(BarangService::class)->buat([
        'nama' => 'Laptop',
        'kategori_id' => $kategoriA->id,
        'jumlah_awal' => 10,
    ], $admin->id);
    $itemB = app(BarangService::class)->buat([
        'nama' => 'Speaker',
        'kategori_id' => $kategoriB->id,
        'jumlah_awal' => 10,
    ], $admin->id);

    $pinjam = function (Item $item, array $override = []) use ($tanggal, $divisiIT): Loan {
        return app(PeminjamanService::class)->buat(array_merge([
            'nama_peminjam' => 'Budi Santoso',
            'division_id' => $divisiIT->id,
            'tipe_peminjam' => 'karyawan',
            'telepon' => '081234567890',
            'tanggal_pinjam' => $tanggal(0),
            'batas_kembali' => $tanggal(3),
            'idempotency_key' => (string) Str::uuid(),
            'items' => [['item_id' => $item->id, 'qty' => 2]],
        ], $override));
    };

    $dipesan = $pinjam($itemA, [
        'nama_peminjam' => 'Dipesan Orang',
        'tanggal_pinjam' => $tanggal(2),
        'batas_kembali' => $tanggal(5),
    ]);

    $dipinjam = $pinjam($itemA, [
        'nama_peminjam' => 'Budi <script>alert(1)</script>',
    ]);

    $terlambat = $pinjam($itemB, [
        'nama_peminjam' => 'Terlambat Orang',
        'tipe_peminjam' => 'magang',
        'division_id' => $divisiLain->id,
    ]);
    $terlambat->update([
        'tanggal_pinjam' => $tanggal(-10),
        'batas_kembali' => $tanggal(-7),
    ]);

    $menunggu = $pinjam($itemA, [
        'nama_peminjam' => 'Menunggu Orang',
        'items' => [['item_id' => $itemA->id, 'qty' => 1]],
    ]);
    $klaimMenunggu = app(KlaimService::class)->buat([
        'kode_pinjam' => $menunggu->kode_pinjam,
        'idempotency_key' => (string) Str::uuid(),
        'baris' => [['item_id' => $itemA->id, 'baik' => 1, 'rusak' => 0, 'hilang' => 0]],
    ]);
    unset($klaimMenunggu);

    $selesai = $pinjam($itemA, [
        'nama_peminjam' => 'Selesai Orang',
        'items' => [['item_id' => $itemA->id, 'qty' => 1]],
    ]);
    $klaimSelesai = app(KlaimService::class)->buat([
        'kode_pinjam' => $selesai->kode_pinjam,
        'idempotency_key' => (string) Str::uuid(),
        'baris' => [['item_id' => $itemA->id, 'baik' => 1, 'rusak' => 0, 'hilang' => 0]],
    ]);
    $barisKlaim = $klaimSelesai->lines()->first();
    app(VerifikasiService::class)->proses(
        $klaimSelesai->id,
        [['line_id' => $barisKlaim->id, 'qty_baik' => 1, 'qty_rusak' => 0, 'qty_hilang' => 0, 'qty_ditolak' => 0]],
        null,
        $admin->id,
    );

    $dibatalkan = $pinjam($itemA, ['nama_peminjam' => 'Dibatalkan Orang']);
    app(PembatalanService::class)->olehAdmin($dibatalkan->id, $admin->id, 'Salah input');

    return compact('dipesan', 'dipinjam', 'terlambat', 'menunggu', 'selesai', 'dibatalkan', 'itemA', 'itemB', 'kategoriA', 'kategoriB', 'divisiIT', 'divisiLain');
}
