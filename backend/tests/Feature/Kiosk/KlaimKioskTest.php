<?php

use App\Models\Division;
use App\Models\Item;
use App\Models\ReturnClaim;
use App\Services\PeminjamanService;
use App\Support\Tanggal;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SettingSeeder::class);
    RateLimiter::clear('kiosk-tulis:127.0.0.1');
    Cache::flush();
});

it('klaim parsial berhasil dan stok tidak berubah dengan assertInvarian serta sisa tinggal sesuai (T-07, BR-03)', function () {
    $item = Item::factory()->create([
        'total' => 10,
        'tersedia' => 10,
        'rusak' => 0,
    ]);
    $divisi = Division::firstOrCreate(['nama' => 'IT'], ['aktif' => true]);

    $loan = app(PeminjamanService::class)->buat([
        'nama_peminjam' => 'Budi Santoso',
        'division_id' => $divisi->id,
        'tipe_peminjam' => 'karyawan',
        'tanggal_pinjam' => Tanggal::hariIni(),
        'batas_kembali' => (new DateTimeImmutable(Tanggal::hariIni(), new DateTimeZone('Asia/Jakarta')))->modify('+3 days')->format('Y-m-d'),
        'idempotency_key' => (string) Str::uuid(),
        'items' => [
            ['item_id' => $item->id, 'qty' => 3],
        ],
    ]);

    $itemSegarSebelumKlaim = $item->fresh();
    expect($itemSegarSebelumKlaim->tersedia)->toBe(7)
        ->and($itemSegarSebelumKlaim->rusak)->toBe(0)
        ->and($itemSegarSebelumKlaim->total)->toBe(10);

    // Klaim parsial: 2 dari 3
    $res = spa()->postJson('/api/kiosk/return-claims', [
        'kode' => $loan->kode_pinjam,
        'idempotency_key' => (string) Str::uuid(),
        'catatan' => 'Sebagian dulu',
        'lines' => [
            ['item_id' => $item->id, 'kondisi' => 'baik', 'qty' => 2],
        ],
    ]);

    $res->assertStatus(201)
        ->assertJsonPath('data.kode_pinjam', $loan->kode_pinjam)
        ->assertJsonPath('data.status', 'menunggu')
        ->assertJsonPath('data.hari_terlambat', 0)
        ->assertJsonPath('data.catatan_peminjam', 'Sebagian dulu')
        ->assertJsonPath('data.lines.0.nama', $item->nama)
        ->assertJsonPath('data.lines.0.kondisi_dilaporkan', 'baik')
        ->assertJsonPath('data.lines.0.qty', 2);

    // Stok TIDAK berubah (tersedia 7, rusak 0, total 10)
    $itemSegarSetelahKlaim = $item->fresh();
    expect($itemSegarSetelahKlaim->tersedia)->toBe(7)
        ->and($itemSegarSetelahKlaim->rusak)->toBe(0)
        ->and($itemSegarSetelahKlaim->total)->toBe(10);

    assertInvarian(false);

    // Sisa tinggal 1 (diperiksa via lookup)
    $resLookup = spa()->postJson('/api/kiosk/loans/lookup', ['kode' => $loan->kode_pinjam]);
    $resLookup->assertOk()
        ->assertJsonPath('data.items.0.sisa', 1)
        ->assertJsonPath('data.items.0.menunggu', 2);
});

it('klaim melebihi sisa ditolak termasuk saat unit sedang menunggu verifikasi (T-08)', function () {
    $item = Item::factory()->create(['total' => 5, 'tersedia' => 5, 'rusak' => 0]);
    $divisi = Division::firstOrCreate(['nama' => 'IT'], ['aktif' => true]);

    $loan = app(PeminjamanService::class)->buat([
        'nama_peminjam' => 'Budi Santoso',
        'division_id' => $divisi->id,
        'tipe_peminjam' => 'karyawan',
        'tanggal_pinjam' => Tanggal::hariIni(),
        'batas_kembali' => (new DateTimeImmutable(Tanggal::hariIni(), new DateTimeZone('Asia/Jakarta')))->modify('+3 days')->format('Y-m-d'),
        'idempotency_key' => (string) Str::uuid(),
        'items' => [
            ['item_id' => $item->id, 'qty' => 3],
        ],
    ]);

    // Klaim pertama: 2 unit
    spa()->postJson('/api/kiosk/return-claims', [
        'kode' => $loan->kode_pinjam,
        'idempotency_key' => (string) Str::uuid(),
        'lines' => [
            ['item_id' => $item->id, 'kondisi' => 'baik', 'qty' => 2],
        ],
    ])->assertStatus(201);

    // Klaim kedua mencoba mengklaim 2 lagi padahal sisa tinggal 1 (karena 2 sedang menunggu)
    RateLimiter::clear('kiosk-tulis:127.0.0.1');
    $res = spa()->postJson('/api/kiosk/return-claims', [
        'kode' => $loan->kode_pinjam,
        'idempotency_key' => (string) Str::uuid(),
        'lines' => [
            ['item_id' => $item->id, 'kondisi' => 'baik', 'qty' => 2],
        ],
    ]);

    $res->assertStatus(409)
        ->assertJson([
            'kode' => 'klaim_melebihi_sisa',
        ]);

    expect($res->json('pesan'))->toContain('1 unit');

    assertInvarian(false);
});

it('satu klaim campuran baik, rusak, hilang pada beberapa barang diproses benar (T-09)', function () {
    $itemA = Item::factory()->create(['total' => 10, 'tersedia' => 10, 'rusak' => 0]);
    $itemB = Item::factory()->create(['total' => 10, 'tersedia' => 10, 'rusak' => 0]);
    $divisi = Division::firstOrCreate(['nama' => 'IT'], ['aktif' => true]);

    $loan = app(PeminjamanService::class)->buat([
        'nama_peminjam' => 'Budi Santoso',
        'division_id' => $divisi->id,
        'tipe_peminjam' => 'karyawan',
        'tanggal_pinjam' => Tanggal::hariIni(),
        'batas_kembali' => (new DateTimeImmutable(Tanggal::hariIni(), new DateTimeZone('Asia/Jakarta')))->modify('+3 days')->format('Y-m-d'),
        'idempotency_key' => (string) Str::uuid(),
        'items' => [
            ['item_id' => $itemA->id, 'qty' => 3],
            ['item_id' => $itemB->id, 'qty' => 2],
        ],
    ]);

    $res = spa()->postJson('/api/kiosk/return-claims', [
        'kode' => $loan->kode_pinjam,
        'idempotency_key' => (string) Str::uuid(),
        'catatan' => 'Klaim campuran',
        'lines' => [
            ['item_id' => $itemA->id, 'kondisi' => 'baik', 'qty' => 1],
            ['item_id' => $itemA->id, 'kondisi' => 'rusak', 'qty' => 1],
            ['item_id' => $itemA->id, 'kondisi' => 'hilang', 'qty' => 1],
            ['item_id' => $itemB->id, 'kondisi' => 'baik', 'qty' => 1],
            ['item_id' => $itemB->id, 'kondisi' => 'rusak', 'qty' => 1],
        ],
    ]);

    $res->assertStatus(201)
        ->assertJsonPath('data.kode_pinjam', $loan->kode_pinjam)
        ->assertJsonPath('data.status', 'menunggu')
        ->assertJsonCount(5, 'data.lines');

    expect(ReturnClaim::where('loan_id', $loan->id)->first()->lines)->toHaveCount(5);

    assertInvarian(false);
});

it('klaim atas pemesanan ditolak (T-25)', function () {
    $item = Item::factory()->create(['total' => 5, 'tersedia' => 5, 'rusak' => 0]);
    $divisi = Division::firstOrCreate(['nama' => 'IT'], ['aktif' => true]);
    $besok = (new DateTimeImmutable(Tanggal::hariIni(), new DateTimeZone('Asia/Jakarta')))->modify('+1 days')->format('Y-m-d');

    $loan = app(PeminjamanService::class)->buat([
        'nama_peminjam' => 'Budi Santoso',
        'division_id' => $divisi->id,
        'tipe_peminjam' => 'karyawan',
        'tanggal_pinjam' => $besok,
        'batas_kembali' => (new DateTimeImmutable($besok, new DateTimeZone('Asia/Jakarta')))->modify('+3 days')->format('Y-m-d'),
        'idempotency_key' => (string) Str::uuid(),
        'items' => [
            ['item_id' => $item->id, 'qty' => 1],
        ],
    ]);

    $res = spa()->postJson('/api/kiosk/return-claims', [
        'kode' => $loan->kode_pinjam,
        'idempotency_key' => (string) Str::uuid(),
        'lines' => [
            ['item_id' => $item->id, 'kondisi' => 'baik', 'qty' => 1],
        ],
    ]);

    $res->assertStatus(409)
        ->assertJson([
            'kode' => 'pemesanan_belum_dimulai',
        ]);
});

it('hari_terlambat dibekukan pada klaim terlambat (T-12)', function () {
    $item = Item::factory()->create(['total' => 5, 'tersedia' => 5, 'rusak' => 0]);
    $divisi = Division::firstOrCreate(['nama' => 'IT'], ['aktif' => true]);

    $loan = app(PeminjamanService::class)->buat([
        'nama_peminjam' => 'Budi Santoso',
        'division_id' => $divisi->id,
        'tipe_peminjam' => 'karyawan',
        'tanggal_pinjam' => Tanggal::hariIni(),
        'batas_kembali' => (new DateTimeImmutable(Tanggal::hariIni(), new DateTimeZone('Asia/Jakarta')))->modify('+3 days')->format('Y-m-d'),
        'idempotency_key' => (string) Str::uuid(),
        'items' => [
            ['item_id' => $item->id, 'qty' => 1],
        ],
    ]);

    // Backdate batas_kembali 4 hari lalu (dan tanggal_pinjam 7 hari lalu agar lolos check constraint)
    $tujuhHariLalu = (new DateTimeImmutable(Tanggal::hariIni(), new DateTimeZone('Asia/Jakarta')))->modify('-7 days')->format('Y-m-d');
    $empatHariLalu = (new DateTimeImmutable(Tanggal::hariIni(), new DateTimeZone('Asia/Jakarta')))->modify('-4 days')->format('Y-m-d');
    $loan->update([
        'tanggal_pinjam' => $tujuhHariLalu,
        'batas_kembali' => $empatHariLalu,
    ]);

    $res = spa()->postJson('/api/kiosk/return-claims', [
        'kode' => $loan->kode_pinjam,
        'idempotency_key' => (string) Str::uuid(),
        'lines' => [
            ['item_id' => $item->id, 'kondisi' => 'baik', 'qty' => 1],
        ],
    ]);

    $res->assertStatus(201)
        ->assertJsonPath('data.hari_terlambat', 4);

    $klaim = ReturnClaim::where('loan_id', $loan->id)->first();
    expect($klaim->hari_terlambat)->toBe(4);
});

it('replay dengan idempotency_key sama menghasilkan satu klaim, status 201 lalu 200, badan sama (T-11)', function () {
    $item = Item::factory()->create(['total' => 5, 'tersedia' => 5, 'rusak' => 0]);
    $divisi = Division::firstOrCreate(['nama' => 'IT'], ['aktif' => true]);

    $loan = app(PeminjamanService::class)->buat([
        'nama_peminjam' => 'Budi Santoso',
        'division_id' => $divisi->id,
        'tipe_peminjam' => 'karyawan',
        'tanggal_pinjam' => Tanggal::hariIni(),
        'batas_kembali' => (new DateTimeImmutable(Tanggal::hariIni(), new DateTimeZone('Asia/Jakarta')))->modify('+3 days')->format('Y-m-d'),
        'idempotency_key' => (string) Str::uuid(),
        'items' => [
            ['item_id' => $item->id, 'qty' => 2],
        ],
    ]);

    $kunci = (string) Str::uuid();
    $payload = [
        'kode' => $loan->kode_pinjam,
        'idempotency_key' => $kunci,
        'catatan' => 'Catatan tes replay',
        'lines' => [
            ['item_id' => $item->id, 'kondisi' => 'baik', 'qty' => 1],
        ],
    ];

    $res1 = spa()->postJson('/api/kiosk/return-claims', $payload);
    $res1->assertStatus(201);

    RateLimiter::clear('kiosk-tulis:127.0.0.1');

    $res2 = spa()->postJson('/api/kiosk/return-claims', $payload);
    $res2->assertStatus(200);

    expect($res1->json('data'))->toBe($res2->json('data'))
        ->and(ReturnClaim::where('idempotency_key', $kunci)->count())->toBe(1);
});

it('kode tidak ditemukan menghasilkan respons IDENTIK dengan lookup dan menaikkan penghitung PembatasKodeKiosk yang SAMA dengan lookup', function () {
    $resLookup = spa()->postJson('/api/kiosk/loans/lookup', ['kode' => 'PJM-999999']);
    $resKlaim = spa()->postJson('/api/kiosk/return-claims', [
        'kode' => 'PJM-999999',
        'idempotency_key' => (string) Str::uuid(),
        'lines' => [
            ['item_id' => 1, 'kondisi' => 'baik', 'qty' => 1],
        ],
    ]);

    expect($resLookup->status())->toBe(404)
        ->and($resKlaim->status())->toBe(404)
        ->and($resKlaim->json())->toBe($resLookup->json())
        ->and($resKlaim->json())->toBe([
            'kode' => 'kode_tidak_ditemukan',
            'pesan' => 'Kode peminjaman tidak ditemukan.',
        ]);

    // Dua gagal di lookup (sudah 1 di atas, tambah 1)
    RateLimiter::clear('kiosk-tulis:127.0.0.1');
    spa()->postJson('/api/kiosk/loans/lookup', ['kode' => 'PJM-999999'])->assertStatus(404);

    // Ditambah 3 di klaim (sudah 1 di atas, tambah 2 lagi) = total 5
    RateLimiter::clear('kiosk-tulis:127.0.0.1');
    spa()->postJson('/api/kiosk/return-claims', [
        'kode' => 'PJM-999999',
        'idempotency_key' => (string) Str::uuid(),
        'lines' => [['item_id' => 1, 'kondisi' => 'baik', 'qty' => 1]],
    ])->assertStatus(404);

    RateLimiter::clear('kiosk-tulis:127.0.0.1');
    spa()->postJson('/api/kiosk/return-claims', [
        'kode' => 'PJM-999999',
        'idempotency_key' => (string) Str::uuid(),
        'lines' => [['item_id' => 1, 'kondisi' => 'baik', 'qty' => 1]],
    ])->assertStatus(404);

    // Sekarang total gagal = 5. Percobaan berikutnya harus 429
    RateLimiter::clear('kiosk-tulis:127.0.0.1');
    $resTerkunci = spa()->postJson('/api/kiosk/return-claims', [
        'kode' => 'PJM-999999',
        'idempotency_key' => (string) Str::uuid(),
        'lines' => [['item_id' => 1, 'kondisi' => 'baik', 'qty' => 1]],
    ]);

    $resTerkunci->assertStatus(429)
        ->assertJson([
            'kode' => 'lookup_terkunci',
        ])
        ->assertHeader('Retry-After');
});

it('setelah terkunci klaim dengan kode benar tetap 429', function () {
    $item = Item::factory()->create(['total' => 5, 'tersedia' => 5, 'rusak' => 0]);
    $divisi = Division::firstOrCreate(['nama' => 'IT'], ['aktif' => true]);

    $loan = app(PeminjamanService::class)->buat([
        'nama_peminjam' => 'Budi Santoso',
        'division_id' => $divisi->id,
        'tipe_peminjam' => 'karyawan',
        'tanggal_pinjam' => Tanggal::hariIni(),
        'batas_kembali' => (new DateTimeImmutable(Tanggal::hariIni(), new DateTimeZone('Asia/Jakarta')))->modify('+3 days')->format('Y-m-d'),
        'idempotency_key' => (string) Str::uuid(),
        'items' => [
            ['item_id' => $item->id, 'qty' => 1],
        ],
    ]);

    // Kunci IP dengan 5 gagal
    for ($i = 0; $i < 5; $i++) {
        RateLimiter::clear('kiosk-tulis:127.0.0.1');
        spa()->postJson('/api/kiosk/return-claims', [
            'kode' => 'PJM-999999',
            'idempotency_key' => (string) Str::uuid(),
            'lines' => [['item_id' => 1, 'kondisi' => 'baik', 'qty' => 1]],
        ])->assertStatus(404);
    }

    // Klaim dengan kode BENAR harus 429
    RateLimiter::clear('kiosk-tulis:127.0.0.1');
    $res = spa()->postJson('/api/kiosk/return-claims', [
        'kode' => $loan->kode_pinjam,
        'idempotency_key' => (string) Str::uuid(),
        'lines' => [['item_id' => $item->id, 'kondisi' => 'baik', 'qty' => 1]],
    ]);

    $res->assertStatus(429)
        ->assertJson([
            'kode' => 'lookup_terkunci',
        ]);
});

it('respons tidak memuat telepon dan tidak memuat tersedia, total, atau rusak', function () {
    $item = Item::factory()->create(['total' => 5, 'tersedia' => 5, 'rusak' => 0]);
    $divisi = Division::firstOrCreate(['nama' => 'IT'], ['aktif' => true]);

    $loan = app(PeminjamanService::class)->buat([
        'nama_peminjam' => 'Budi Santoso',
        'division_id' => $divisi->id,
        'tipe_peminjam' => 'karyawan',
        'telepon' => '081234567890',
        'tanggal_pinjam' => Tanggal::hariIni(),
        'batas_kembali' => (new DateTimeImmutable(Tanggal::hariIni(), new DateTimeZone('Asia/Jakarta')))->modify('+3 days')->format('Y-m-d'),
        'idempotency_key' => (string) Str::uuid(),
        'items' => [
            ['item_id' => $item->id, 'qty' => 1],
        ],
    ]);

    $res = spa()->postJson('/api/kiosk/return-claims', [
        'kode' => $loan->kode_pinjam,
        'idempotency_key' => (string) Str::uuid(),
        'lines' => [
            ['item_id' => $item->id, 'kondisi' => 'baik', 'qty' => 1],
        ],
    ]);

    $res->assertStatus(201);
    $json = $res->json();

    $terlarang = ['telepon', 'tersedia', 'total', 'rusak'];
    $periksa = function ($data) use (&$periksa, $terlarang) {
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                // Periksa key tidak boleh mengandung kata terlarang (kecuali value dari kondisi_dilaporkan: 'rusak')
                if ($k !== 'kondisi_dilaporkan') {
                    foreach ($terlarang as $kata) {
                        expect(strtolower((string) $k))->not->toBe($kata);
                    }
                }
                $periksa($v);
            }
        }
    };

    expect($json)->not->toHaveKey('telepon')
        ->and($json['data'])->not->toHaveKey('id')
        ->and($json['data'])->not->toHaveKey('loan_id')
        ->and($json['data'])->not->toHaveKey('telepon');

    $periksa($json);
});
