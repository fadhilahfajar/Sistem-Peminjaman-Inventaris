<?php

use App\Exceptions\GalatBisnis;
use App\Models\Category;
use App\Models\Division;
use App\Models\Item;
use App\Models\Loan;
use App\Models\ReturnClaim;
use App\Models\User;
use App\Services\KlaimService;
use App\Services\KoreksiService;
use App\Services\PembatalanService;
use App\Services\PeminjamanService;
use App\Services\PerpanjanganService;
use App\Services\StokService;
use App\Services\VerifikasiService;
use App\Support\BukuBesarStok;
use App\Support\Tanggal;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function tglAcak(int $offset): string
{
    return (new DateTimeImmutable(Tanggal::hariIni(), new DateTimeZone('Asia/Jakarta')))
        ->modify("{$offset} days")->format('Y-m-d');
}

it('1.000 operasi acak dengan seed tetap menjaga invarian 0 pelanggaran (T-10)', function () {
    $this->seed(SettingSeeder::class);

    // Tetapkan seed acak agar deterministik
    mt_srand(20261105);

    // Siapkan admin
    $admin = User::create([
        'name' => 'Admin Acak',
        'email' => 'admin-acak@example.test',
        'password' => 'password-aman-12345',
        'aktif' => true,
    ]);

    // Siapkan divisi & kategori
    $divisi1 = Division::create(['nama' => 'IT Acak', 'aktif' => true]);
    $divisi2 = Division::create(['nama' => 'Operasional Acak', 'aktif' => true]);
    $divisiList = [$divisi1, $divisi2];

    $kategori = Category::create(['nama' => 'Perangkat Acak', 'aktif' => true]);

    // Siapkan 5 barang awal dengan stok buku besar
    $items = [];
    for ($i = 1; $i <= 5; $i++) {
        $stokAwal = 20;
        $item = Item::create([
            'nama' => "Barang Acak {$i}",
            'kategori_id' => $kategori->id,
            'total' => $stokAwal,
            'tersedia' => $stokAwal,
            'rusak' => 0,
            'boleh_diperpanjang' => ($i % 2 === 1),
        ]);
        BukuBesarStok::catat($item, 'TAMBAH_STOK', $stokAwal, $stokAwal, 0, 'items', $item->id, $admin->id, 'Stok awal');
        $items[] = $item;
    }

    $peminjamanService = app(PeminjamanService::class);
    $klaimService = app(KlaimService::class);
    $verifikasiService = app(VerifikasiService::class);
    $pembatalanService = app(PembatalanService::class);
    $perpanjanganService = app(PerpanjanganService::class);
    $stokService = app(StokService::class);
    $koreksiService = app(KoreksiService::class);

    $totalOperasi = 1000;
    $berhasil = 0;
    $ditolak = 0;

    for ($op = 1; $op <= $totalOperasi; $op++) {
        $tipe = mt_rand(1, 11);

        try {
            switch ($tipe) {
                case 1: // Pinjam hari ini
                    $item = $items[array_rand($items)];
                    $div = $divisiList[array_rand($divisiList)];
                    $peminjamanService->buat([
                        'nama_peminjam' => 'Peminjam '.mt_rand(1, 50),
                        'division_id' => $div->id,
                        'tipe_peminjam' => mt_rand(0, 1) ? 'karyawan' : 'magang',
                        'telepon' => '0812'.mt_rand(10000000, 99999999),
                        'tanggal_pinjam' => tglAcak(0),
                        'batas_kembali' => tglAcak(mt_rand(1, 5)),
                        'idempotency_key' => (string) Str::uuid(),
                        'items' => [['item_id' => $item->id, 'qty' => mt_rand(1, 3)]],
                    ]);
                    $berhasil++;
                    break;

                case 2: // Pesan untuk masa depan
                    $item = $items[array_rand($items)];
                    $div = $divisiList[array_rand($divisiList)];
                    $offsetMulai = mt_rand(1, 3);
                    $peminjamanService->buat([
                        'nama_peminjam' => 'Pemesan '.mt_rand(1, 50),
                        'division_id' => $div->id,
                        'tipe_peminjam' => 'karyawan',
                        'telepon' => '0813'.mt_rand(10000000, 99999999),
                        'tanggal_pinjam' => tglAcak($offsetMulai),
                        'batas_kembali' => tglAcak($offsetMulai + mt_rand(1, 4)),
                        'idempotency_key' => (string) Str::uuid(),
                        'items' => [['item_id' => $item->id, 'qty' => mt_rand(1, 2)]],
                    ]);
                    $berhasil++;
                    break;

                case 3: // Klaim
                    $loan = Loan::whereNull('dibatalkan_pada')->inRandomOrder()->first();
                    if (! $loan) {
                        $item = $items[array_rand($items)];
                        $div = $divisiList[array_rand($divisiList)];
                        $peminjamanService->buat([
                            'nama_peminjam' => 'Peminjam Pengganti',
                            'division_id' => $div->id,
                            'tipe_peminjam' => 'karyawan',
                            'telepon' => '081211112222',
                            'tanggal_pinjam' => tglAcak(0),
                            'batas_kembali' => tglAcak(3),
                            'idempotency_key' => (string) Str::uuid(),
                            'items' => [['item_id' => $item->id, 'qty' => 1]],
                        ]);
                        $berhasil++;
                        break;
                    }
                    $loanItem = $loan->items()->inRandomOrder()->first();
                    if (! $loanItem) {
                        break;
                    }
                    $qtyKlaim = mt_rand(1, 2);
                    $klaimService->buat([
                        'kode_pinjam' => $loan->kode_pinjam,
                        'idempotency_key' => (string) Str::uuid(),
                        'baris' => [
                            [
                                'item_id' => $loanItem->item_id,
                                'baik' => $qtyKlaim,
                                'rusak' => 0,
                                'hilang' => 0,
                            ],
                        ],
                    ]);
                    $berhasil++;
                    break;

                case 4: // Verifikasi
                    $claim = ReturnClaim::where('status', 'menunggu')->inRandomOrder()->first();
                    if (! $claim) {
                        $item = $items[array_rand($items)];
                        $stokService->tambah($item->id, 2, $admin->id, 'Pengganti verif');
                        $berhasil++;
                        break;
                    }
                    $lines = $claim->lines()->get();
                    $hasil = [];
                    foreach ($lines as $line) {
                        $hasil[] = [
                            'line_id' => $line->id,
                            'qty_baik' => $line->kondisi_dilaporkan === 'baik' ? $line->qty : 0,
                            'qty_rusak' => $line->kondisi_dilaporkan === 'rusak' ? $line->qty : 0,
                            'qty_hilang' => $line->kondisi_dilaporkan === 'hilang' ? $line->qty : 0,
                            'qty_ditolak' => 0,
                        ];
                    }
                    $verifikasiService->proses($claim->id, $hasil, null, $admin->id);
                    $berhasil++;
                    break;

                case 5: // Batal
                    $loan = Loan::whereNull('dibatalkan_pada')->inRandomOrder()->first();
                    if (! $loan) {
                        $item = $items[array_rand($items)];
                        $stokService->tambah($item->id, 1, $admin->id, 'Pengganti batal');
                        $berhasil++;
                        break;
                    }
                    if (mt_rand(0, 1)) {
                        $pembatalanService->olehPeminjam($loan->kode_pinjam);
                    } else {
                        $pembatalanService->olehAdmin($loan->id, $admin->id, 'Batal uji acak');
                    }
                    $berhasil++;
                    break;

                case 6: // Perpanjang
                    $loan = Loan::whereNull('dibatalkan_pada')->inRandomOrder()->first();
                    if (! $loan) {
                        $item = $items[array_rand($items)];
                        $stokService->tambah($item->id, 1, $admin->id, 'Pengganti perpanjang');
                        $berhasil++;
                        break;
                    }
                    if (mt_rand(0, 1)) {
                        $perpanjanganService->olehPeminjam($loan->kode_pinjam, mt_rand(1, 2));
                    } else {
                        $perpanjanganService->olehAdmin($loan->id, mt_rand(1, 3), $admin->id, 'Disetujui admin');
                    }
                    $berhasil++;
                    break;

                case 7: // Tambah / kurangi stok
                    $item = $items[array_rand($items)];
                    if (mt_rand(0, 1)) {
                        $stokService->tambah($item->id, mt_rand(1, 5), $admin->id, 'Restock acak');
                    } else {
                        $stokService->kurangi($item->id, mt_rand(1, 2), $admin->id, 'Kurang stok acak');
                    }
                    $berhasil++;
                    break;

                case 8: // Tandai rusak
                    $item = $items[array_rand($items)];
                    $stokService->tandaiRusak($item->id, mt_rand(1, 2), $admin->id, 'Rusak pemakaian');
                    $berhasil++;
                    break;

                case 9: // Selesai diperbaiki
                    $item = $items[array_rand($items)];
                    $stokService->selesaiPerbaikan($item->id, mt_rand(1, 2), $admin->id, 'Selesai servis');
                    $berhasil++;
                    break;

                case 10: // Afkir
                    $item = $items[array_rand($items)];
                    $stokService->afkir($item->id, mt_rand(1, 2), $admin->id, 'Tidak bisa diperbaiki');
                    $berhasil++;
                    break;

                case 11: // Koreksi peminjaman
                    $loan = Loan::whereNull('dibatalkan_pada')->inRandomOrder()->first();
                    if (! $loan) {
                        $item = $items[array_rand($items)];
                        $stokService->tambah($item->id, 1, $admin->id, 'Pengganti koreksi');
                        $berhasil++;
                        break;
                    }
                    $koreksiService->koreksi($loan->id, $admin->id, [
                        'nama_peminjam' => 'Peminjam Terkoreksi '.mt_rand(1, 100),
                    ], 'Koreksi nama peminjam');
                    $berhasil++;
                    break;
            }
        } catch (GalatBisnis|ValidationException $e) {
            // Penolakan aturan bisnis wajar dalam simulasi acak
            $ditolak++;
        }
    }

    expect($berhasil + $ditolak)->toBe($totalOperasi)
        ->and($berhasil)->toBeGreaterThan(100);

    // Verifikasi periksa-invarian melaporkan 0 pelanggaran (T-10)
    assertInvarian();

    // Verifikasi via command artisan inventaris:periksa-invarian
    $exitCode = Artisan::call('inventaris:periksa-invarian');
    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('0 pelanggaran');
});
