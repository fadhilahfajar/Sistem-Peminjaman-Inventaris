<?php

use App\Models\Category;
use App\Models\Division;
use App\Models\Item;
use App\Models\Loan;
use App\Models\StockMovement;
use App\Support\Tanggal;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SettingSeeder::class);
    RateLimiter::clear('kiosk-tulis:127.0.0.1');
});

function tglOffset(int $hari, ?string $dari = null): string
{
    $base = $dari ?? Tanggal::hariIni();

    return (new DateTimeImmutable($base, new DateTimeZone('Asia/Jakarta')))
        ->modify("{$hari} days")
        ->format('Y-m-d');
}

function payloadPeminjaman(array $override = []): array
{
    $kategori = Category::factory()->create();
    $item = Item::factory()->create([
        'nama' => 'Barang '.uniqid(),
        'kategori_id' => $kategori->id,
        'total' => 10,
        'tersedia' => 10,
        'rusak' => 0,
    ]);
    $divisi = Division::firstOrCreate(['nama' => 'IT'], ['aktif' => true]);

    $hariIni = Tanggal::hariIni();

    return array_merge([
        'nama_peminjam' => 'Budi Santoso',
        'division_id' => $divisi->id,
        'tipe_peminjam' => 'karyawan',
        'telepon' => '081234567890',
        'tanggal_pinjam' => $hariIni,
        'batas_kembali' => tglOffset(3, $hariIni),
        'idempotency_key' => (string) Str::uuid(),
        'items' => [
            [
                'item_id' => $item->id,
                'qty' => 3,
            ],
        ],
    ], $override);
}

it('peminjaman hari ini berhasil dan stok barang berkurang tepat sejumlah qty dengan satu baris stock_movements jenis PINJAM per barang', function () {
    $payload = payloadPeminjaman();
    $itemId = $payload['items'][0]['item_id'];

    $response = spa()->postJson('/api/kiosk/loans', $payload);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => [
                'kode_pinjam',
                'nama_peminjam',
                'division' => ['id', 'nama'],
                'tipe_peminjam',
                'tanggal_pinjam',
                'batas_kembali',
                'status',
                'pemesanan',
                'items' => [
                    '*' => ['nama', 'qty_dipinjam'],
                ],
            ],
        ]);

    $item = Item::findOrFail($itemId);

    expect($response->json('data.status'))->toBe('dipinjam')
        ->and($response->json('data.pemesanan'))->toBeFalse()
        ->and($response->json('data.items.0.nama'))->toBe($item->nama)
        ->and($response->json('data.items.0.qty_dipinjam'))->toBe(3);

    expect($item->tersedia)->toBe(7)
        ->and($item->total)->toBe(10)
        ->and($item->rusak)->toBe(0);

    $mutasi = StockMovement::where('item_id', $itemId)->where('jenis', 'PINJAM')->get();
    expect($mutasi)->toHaveCount(1)
        ->and($mutasi->first()->delta_tersedia)->toBe(-3)
        ->and($mutasi->first()->delta_total)->toBe(0)
        ->and($mutasi->first()->delta_rusak)->toBe(0);

    assertInvarian(false);
});

it('pemesanan tanggal depan berhasil (status Dipesan) dan stok langsung ditahan (T-23)', function () {
    $hariIni = Tanggal::hariIni();
    $tanggalDepan = tglOffset(2, $hariIni);
    $batasKembali = tglOffset(5, $hariIni);

    $payload = payloadPeminjaman([
        'tanggal_pinjam' => $tanggalDepan,
        'batas_kembali' => $batasKembali,
    ]);
    $itemId = $payload['items'][0]['item_id'];

    $response = spa()->postJson('/api/kiosk/loans', $payload);

    $response->assertStatus(201)
        ->assertJson([
            'data' => [
                'status' => 'dipesan',
                'pemesanan' => true,
                'tanggal_pinjam' => $tanggalDepan,
                'batas_kembali' => $batasKembali,
            ],
        ]);

    $item = Item::findOrFail($itemId);
    expect($item->tersedia)->toBe(7);

    assertInvarian(false);
});

it('qty melebihi tersedia ditolak 409 tanpa mengubah stok dan tanpa baris loans (T-02)', function () {
    $payload = payloadPeminjaman();
    $itemId = $payload['items'][0]['item_id'];
    $payload['items'][0]['qty'] = 15; // tersedia hanya 10

    $response = spa()->postJson('/api/kiosk/loans', $payload);

    $response->assertStatus(409)
        ->assertJson([
            'kode' => 'stok_tidak_cukup',
        ]);

    $item = Item::findOrFail($itemId);

    expect($response->json('pesan'))->toContain($item->nama)
        ->and($response->json('pesan'))->toContain('10');

    expect($item->tersedia)->toBe(10);
    expect(Loan::count())->toBe(0);
    expect(StockMovement::where('jenis', 'PINJAM')->count())->toBe(0);

    assertInvarian(false);
});

it('menolak barang ganda dengan 422', function () {
    $payload = payloadPeminjaman();
    $itemId = $payload['items'][0]['item_id'];

    $payload['items'] = [
        ['item_id' => $itemId, 'qty' => 1],
        ['item_id' => $itemId, 'qty' => 2],
    ];

    $response = spa()->postJson('/api/kiosk/loans', $payload);

    $response->assertStatus(422)
        ->assertJson([
            'kode' => 'validasi_gagal',
        ]);
});

it('menolak divisi nonaktif dengan 422', function () {
    $divisiNonaktif = Division::factory()->create(['nama' => 'Nonaktif', 'aktif' => false]);
    $payload = payloadPeminjaman(['division_id' => $divisiNonaktif->id]);

    $response = spa()->postJson('/api/kiosk/loans', $payload);

    $response->assertStatus(422)
        ->assertJson([
            'kode' => 'validasi_gagal',
        ])
        ->assertJsonValidationErrors(['division_id']);
});

it('menolak tipe peminjam selain karyawan atau magang dengan 422 (T-32)', function () {
    $payload = payloadPeminjaman(['tipe_peminjam' => 'tamu']);

    $response = spa()->postJson('/api/kiosk/loans', $payload);

    $response->assertStatus(422)
        ->assertJson([
            'kode' => 'validasi_gagal',
        ])
        ->assertJsonValidationErrors(['tipe_peminjam']);
});

it('menolak telepon 7 digit dengan 422', function () {
    $payload = payloadPeminjaman(['telepon' => '1234567']);

    $response = spa()->postJson('/api/kiosk/loans', $payload);

    $response->assertStatus(422)
        ->assertJson([
            'kode' => 'validasi_gagal',
        ])
        ->assertJsonValidationErrors(['telepon']);
});

it('menolak tanggal lampau atau melebihi maks_booking_hari sesuai respons Service', function () {
    $hariIni = Tanggal::hariIni();

    // Tanggal lampau
    $payloadLampau = payloadPeminjaman([
        'tanggal_pinjam' => tglOffset(-1, $hariIni),
        'batas_kembali' => tglOffset(2, $hariIni),
    ]);

    $resLampau = spa()->postJson('/api/kiosk/loans', $payloadLampau);
    $resLampau->assertStatus(422)
        ->assertJson([
            'kode' => 'validasi_gagal',
        ])
        ->assertJsonValidationErrors(['tanggal_pinjam']);

    // Melebihi maks_booking_hari (bawaan 7 hari, coba 8 hari)
    $payloadJauh = payloadPeminjaman([
        'tanggal_pinjam' => tglOffset(8, $hariIni),
        'batas_kembali' => tglOffset(10, $hariIni),
    ]);

    $resJauh = spa()->postJson('/api/kiosk/loans', $payloadJauh);
    $resJauh->assertStatus(422)
        ->assertJson([
            'kode' => 'validasi_gagal',
        ])
        ->assertJsonValidationErrors(['tanggal_pinjam']);
});

it('dua permintaan dengan idempotency_key sama menghasilkan satu baris loans dan satu set stock_movements, status 201 lalu 200 dengan badan yang sama (T-11)', function () {
    $payload = payloadPeminjaman();
    $itemId = $payload['items'][0]['item_id'];

    $res1 = spa()->postJson('/api/kiosk/loans', $payload);
    $res1->assertStatus(201);
    $body1 = $res1->json();

    $res2 = spa()->postJson('/api/kiosk/loans', $payload);
    $res2->assertStatus(200);
    $body2 = $res2->json();

    expect($body2)->toBe($body1)
        ->and(Loan::count())->toBe(1)
        ->and(StockMovement::where('item_id', $itemId)->where('jenis', 'PINJAM')->count())->toBe(1);

    $item = Item::findOrFail($itemId);
    expect($item->tersedia)->toBe(7);

    assertInvarian(false);
});

it('respons tidak memuat telepon (BR-18)', function () {
    $payload = payloadPeminjaman(['telepon' => '081234567890']);

    $response = spa()->postJson('/api/kiosk/loans', $payload);

    $response->assertStatus(201);
    expect($response->json('data'))->not->toHaveKey('telepon')
        ->and($response->getContent())->not->toContain('081234567890');
});

it('kode_pinjam berformat PJM- diikuti enam karakter dari alfabet BR-17', function () {
    $payload = payloadPeminjaman();

    $response = spa()->postJson('/api/kiosk/loans', $payload);

    $response->assertStatus(201);
    $kode = $response->json('data.kode_pinjam');
    expect($kode)->toMatch('/^PJM-[A-HJKMNP-Z2-9]{6}$/');
});

it('pemanggilan ke-31 dalam satu menit mendapat 429', function () {
    $payload = payloadPeminjaman();

    // 30 permintaan pertama (menggunakan payload yang sama atau beda)
    for ($i = 1; $i <= 30; $i++) {
        spa()->postJson('/api/kiosk/loans', array_merge($payload, [
            'idempotency_key' => (string) Str::uuid(),
            'items' => [
                ['item_id' => $payload['items'][0]['item_id'], 'qty' => 0], // validasi bentuk 422 tetap dihitung limiter
            ],
        ]));
    }

    // Pemanggilan ke-31 ditolak 429 oleh pembatas laju kiosk-tulis
    $response = spa()->postJson('/api/kiosk/loans', $payload);

    $response->assertStatus(429)
        ->assertJson([
            'kode' => 'terlalu_banyak_percobaan',
            'pesan' => 'Terlalu banyak percobaan. Coba lagi beberapa saat lagi.',
        ])
        ->assertHeader('Retry-After');
});
