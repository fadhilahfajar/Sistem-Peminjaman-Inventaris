<?php

use App\Models\Category;
use App\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('mengembalikan hanya field yang diizinkan pada katalog barang', function () {
    $kategori = Category::factory()->create();
    Item::factory()->create([
        'nama' => 'Laptop Dell Latitude',
        'deskripsi' => 'Rahasia internal',
        'kategori_id' => $kategori->id,
        'total' => 10,
        'tersedia' => 8,
        'rusak' => 2,
    ]);

    $response = $this->getJson('/api/kiosk/items');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'nama', 'tersedia', 'kategori_id'],
            ],
        ]);

    $item = $response->json('data.0');
    expect(array_keys($item))->toEqualCanonicalizing(['id', 'nama', 'tersedia', 'kategori_id'])
        ->and($response->getContent())->not->toContain('Rahasia internal')
        ->and($item)->not->toHaveKey('total')
        ->and($item)->not->toHaveKey('rusak')
        ->and($item)->not->toHaveKey('deskripsi');
});

it('tidak memuat barang yang sudah dihapus secara soft-delete', function () {
    $kategori = Category::factory()->create();
    $aktif = Item::factory()->create([
        'nama' => 'Mouse Logitech Aktif',
        'kategori_id' => $kategori->id,
    ]);
    Item::factory()->terhapus()->create([
        'nama' => 'Mouse Logitech Dihapus',
        'kategori_id' => $kategori->id,
    ]);

    $response = $this->getJson('/api/kiosk/items')->assertOk();

    $namaList = collect($response->json('data'))->pluck('nama');
    expect($namaList)->toContain('Mouse Logitech Aktif')
        ->and($namaList)->not->toContain('Mouse Logitech Dihapus');
});

it('tetap memuat barang yang stok tersedianya 0', function () {
    $kategori = Category::factory()->create();
    Item::factory()->habis()->create([
        'nama' => 'Proyektor Epson Stok Habis',
        'kategori_id' => $kategori->id,
    ]);

    $response = $this->getJson('/api/kiosk/items')->assertOk();

    $item = collect($response->json('data'))->firstWhere('nama', 'Proyektor Epson Stok Habis');
    expect($item)->not->toBeNull()
        ->and($item['tersedia'])->toBe(0);
});

it('dapat memfilter barang dengan pencarian q dan filter kategori', function () {
    $kategoriA = Category::factory()->create(['nama' => 'Elektronik']);
    $kategoriB = Category::factory()->create(['nama' => 'Kabel']);

    Item::factory()->create(['nama' => 'Keyboard Mechanical RGB', 'kategori_id' => $kategoriA->id]);
    Item::factory()->create(['nama' => 'Keyboard Membran Biasa', 'kategori_id' => $kategoriA->id]);
    Item::factory()->create(['nama' => 'Kabel HDMI 2M', 'kategori_id' => $kategoriB->id]);

    // Cari nama saja
    $resQ = $this->getJson('/api/kiosk/items?q=Mechanical')->assertOk();
    expect($resQ->json('data'))->toHaveCount(1)
        ->and($resQ->json('data.0.nama'))->toBe('Keyboard Mechanical RGB');

    // Filter kategori saja
    $resKat = $this->getJson('/api/kiosk/items?kategori='.$kategoriB->id)->assertOk();
    expect($resKat->json('data'))->toHaveCount(1)
        ->and($resKat->json('data.0.nama'))->toBe('Kabel HDMI 2M');

    // Gabungan q dan kategori
    $resGabung = $this->getJson('/api/kiosk/items?q=Keyboard&kategori='.$kategoriA->id)->assertOk();
    expect($resGabung->json('data'))->toHaveCount(2);

    $resNihil = $this->getJson('/api/kiosk/items?q=Keyboard&kategori='.$kategoriB->id)->assertOk();
    expect($resNihil->json('data'))->toBeEmpty();
});

it('meng-escape karakter wildcard persen dan garis bawah pada pencarian q', function () {
    $kategori = Category::factory()->create();

    Item::factory()->create(['nama' => 'Barang 100% Asli', 'kategori_id' => $kategori->id]);
    Item::factory()->create(['nama' => 'Barang_Khusus_Vip', 'kategori_id' => $kategori->id]);
    Item::factory()->create(['nama' => 'Barang Lain Bebas', 'kategori_id' => $kategori->id]);

    // Karakter '%' literal
    $resPersen = $this->getJson('/api/kiosk/items?q=100%')->assertOk();
    expect($resPersen->json('data'))->toHaveCount(1)
        ->and($resPersen->json('data.0.nama'))->toBe('Barang 100% Asli');

    // Karakter '_' literal
    $resUnderscore = $this->getJson('/api/kiosk/items?q=Barang_Khusus')->assertOk();
    expect($resUnderscore->json('data'))->toHaveCount(1)
        ->and($resUnderscore->json('data.0.nama'))->toBe('Barang_Khusus_Vip');
});

it('mengurutkan barang berdasarkan nama secara alfabetis', function () {
    $kategori = Category::factory()->create();

    Item::factory()->create(['nama' => 'Zebra Printer', 'kategori_id' => $kategori->id]);
    Item::factory()->create(['nama' => 'Apple iPad', 'kategori_id' => $kategori->id]);
    Item::factory()->create(['nama' => 'Monitor Samsung', 'kategori_id' => $kategori->id]);

    $response = $this->getJson('/api/kiosk/items')->assertOk();

    $namaList = collect($response->json('data'))->pluck('nama')->all();
    expect($namaList)->toBe(['Apple iPad', 'Monitor Samsung', 'Zebra Printer']);
});
