<?php

use App\Exceptions\GalatBisnis;
use App\Models\Category;
use App\Models\Division;
use App\Models\Item;
use App\Support\PenangananGalat;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SettingSeeder::class);
});

it('keenam rute master data menolak 401 tanpa sesi (T-01)', function () {
    spa()->getJson('/api/admin/categories')->assertStatus(401);
    spa()->postJson('/api/admin/categories')->assertStatus(401);
    spa()->patchJson('/api/admin/categories/1')->assertStatus(401);
    spa()->getJson('/api/admin/divisions')->assertStatus(401);
    spa()->postJson('/api/admin/divisions')->assertStatus(401);
    spa()->patchJson('/api/admin/divisions/1')->assertStatus(401);
});

it('GET menampilkan semua baris aktif dan nonaktif dengan field id, nama, aktif, urut nama', function () {
    Category::factory()->create(['nama' => 'Zebra Kategori', 'aktif' => false]);
    Category::factory()->create(['nama' => 'Audio Video', 'aktif' => true]);

    Division::factory()->create(['nama' => 'Media', 'aktif' => false]);
    Division::factory()->create(['nama' => 'EO', 'aktif' => true]);

    $kat = spa()->actingAs(adminUji(), 'web')->getJson('/api/admin/categories')->assertOk();
    $dataKat = $kat->json('data');
    expect(collect($dataKat)->pluck('nama')->all())->toBe(['Audio Video', 'Zebra Kategori'])
        ->and(array_keys($dataKat[0]))->toEqualCanonicalizing(['id', 'nama', 'aktif'])
        ->and($dataKat[0]['aktif'])->toBeTrue()
        ->and($dataKat[1]['aktif'])->toBeFalse();

    $div = spa()->actingAs(adminUji(), 'web')->getJson('/api/admin/divisions')->assertOk();
    $dataDiv = $div->json('data');
    expect(collect($dataDiv)->pluck('nama')->all())->toBe(['EO', 'Media'])
        ->and(array_keys($dataDiv[0]))->toEqualCanonicalizing(['id', 'nama', 'aktif']);
});

it('POST kategori dan divisi membuat baris aktif dan mengembalikannya', function () {
    $admin = adminUji();

    $kat = spa()->actingAs($admin, 'web')
        ->postJson('/api/admin/categories', ['nama' => 'Elektronik'])
        ->assertCreated()
        ->assertJsonPath('data.nama', 'Elektronik')
        ->assertJsonPath('data.aktif', true);

    expect(Category::query()->count())->toBe(1)
        ->and(Category::query()->first()->nama)->toBe('Elektronik')
        ->and($kat->json('data'))->toHaveKeys(['id', 'nama', 'aktif']);

    spa()->actingAs($admin, 'web')
        ->postJson('/api/admin/divisions', ['nama' => 'Tata Usaha'])
        ->assertCreated()
        ->assertJsonPath('data.nama', 'Tata Usaha')
        ->assertJsonPath('data.aktif', true);

    expect(Division::query()->count())->toBe(1);
});

it('POST dan PATCH menolak isian tidak sah dengan 422', function () {
    $admin = adminUji();
    $kategori = Category::factory()->create(['nama' => 'Ada']);
    $divisi = Division::factory()->create(['nama' => 'Ada']);

    // Nama kosong
    spa()->actingAs($admin, 'web')->postJson('/api/admin/categories', ['nama' => ''])
        ->assertStatus(422)->assertJsonValidationErrors('nama');
    spa()->actingAs($admin, 'web')->postJson('/api/admin/divisions', ['nama' => ''])
        ->assertStatus(422)->assertJsonValidationErrors('nama');

    // Nama satu huruf (kurang dari 2) dan 101 karakter
    spa()->actingAs($admin, 'web')->postJson('/api/admin/categories', ['nama' => 'A'])
        ->assertStatus(422)->assertJsonValidationErrors('nama');
    spa()->actingAs($admin, 'web')->postJson('/api/admin/divisions', ['nama' => str_repeat('x', 101)])
        ->assertStatus(422)->assertJsonValidationErrors('nama');

    // PATCH: nama tidak sah dan aktif bukan boolean
    spa()->actingAs($admin, 'web')->patchJson("/api/admin/categories/{$kategori->id}", ['nama' => 'A'])
        ->assertStatus(422)->assertJsonValidationErrors('nama');
    spa()->actingAs($admin, 'web')->patchJson("/api/admin/divisions/{$divisi->id}", ['aktif' => 'bukan-boolean'])
        ->assertStatus(422)->assertJsonValidationErrors('aktif');
});

it('menolak nama ganda dengan 422, tidak peka huruf besar-kecil', function () {
    $admin = adminUji();
    Category::factory()->create(['nama' => 'Elektronik']);
    Division::factory()->create(['nama' => 'Umum']);
    $kategoriLain = Category::factory()->create(['nama' => 'Furnitur']);
    $divisiLain = Division::factory()->create(['nama' => 'Media']);

    spa()->actingAs($admin, 'web')->postJson('/api/admin/categories', ['nama' => 'ELEKTRONIK'])
        ->assertStatus(422)->assertJsonValidationErrors('nama');

    spa()->actingAs($admin, 'web')->postJson('/api/admin/divisions', ['nama' => 'umum'])
        ->assertStatus(422)->assertJsonValidationErrors('nama');

    // PATCH ke baris lain tetap unik terhadap baris lain
    spa()->actingAs($admin, 'web')->patchJson("/api/admin/categories/{$kategoriLain->id}", ['nama' => 'elektronik'])
        ->assertStatus(422)->assertJsonValidationErrors('nama');
    spa()->actingAs($admin, 'web')->patchJson("/api/admin/divisions/{$divisiLain->id}", ['nama' => 'UMUM'])
        ->assertStatus(422)->assertJsonValidationErrors('nama');

    // PATCH dengan nama sendiri dianggap sah
    $kategori = Category::query()->where('nama', 'Elektronik')->first();
    spa()->actingAs($admin, 'web')->patchJson("/api/admin/categories/{$kategori->id}", ['nama' => 'ELEKTRONIK'])
        ->assertOk();
});

it('PATCH mengubah nama dan atau aktif', function () {
    $admin = adminUji();
    $kategori = Category::factory()->create(['nama' => 'Lama Kategori', 'aktif' => true]);
    $divisi = Division::factory()->create(['nama' => 'Lama Divisi', 'aktif' => true]);

    spa()->actingAs($admin, 'web')->patchJson("/api/admin/categories/{$kategori->id}", [
        'nama' => 'Baru Kategori',
        'aktif' => false,
    ])->assertOk()->assertJsonPath('data.nama', 'Baru Kategori')->assertJsonPath('data.aktif', false);

    expect($kategori->refresh()->nama)->toBe('Baru Kategori')->and($kategori->aktif)->toBeFalse();

    spa()->actingAs($admin, 'web')->patchJson("/api/admin/divisions/{$divisi->id}", ['aktif' => false])
        ->assertOk()->assertJsonPath('data.aktif', false);

    expect($divisi->refresh()->nama)->toBe('Lama Divisi')->and($divisi->aktif)->toBeFalse();
});

it('kategori dinonaktifkan ditolak 409 kategori_masih_dipakai bila masih ada barang aktif (T-30, BR-33)', function () {
    $admin = adminUji();
    $kategori = Category::factory()->create(['nama' => 'Elektronik']);
    $item = Item::factory()->create([
        'nama' => 'Laptop A',
        'kategori_id' => $kategori->id,
    ]);

    $r = spa()->actingAs($admin, 'web')
        ->patchJson("/api/admin/categories/{$kategori->id}", ['aktif' => false]);

    $r->assertStatus(409)
        ->assertJsonPath('kode', 'kategori_masih_dipakai')
        ->assertJsonPath('pesan', 'Kategori ini masih dipakai oleh 1 barang aktif. Pindahkan barang ke kategori lain dulu.');

    expect($item->refresh()->exists)->toBeTrue();
});

it('kategori dapat dinonaktifkan setelah semua barangnya soft-delete (T-30, BR-33)', function () {
    $admin = adminUji();
    $kategori = Category::factory()->create(['nama' => 'Elektronik']);
    Item::factory()->terhapus()->create([
        'nama' => 'Laptop Dihapus',
        'kategori_id' => $kategori->id,
    ]);

    spa()->actingAs($admin, 'web')
        ->patchJson("/api/admin/categories/{$kategori->id}", ['aktif' => false])
        ->assertOk()->assertJsonPath('data.aktif', false);
});

it('mengaktifkan kembali dinonaktifkan divisi dan kategori selalu boleh', function () {
    $admin = adminUji();
    $kategori = Category::factory()->create(['nama' => 'Elektronik', 'aktif' => false]);
    $divisi = Division::factory()->create(['nama' => 'Umum', 'aktif' => false]);

    spa()->actingAs($admin, 'web')
        ->patchJson("/api/admin/categories/{$kategori->id}", ['aktif' => true])
        ->assertOk()->assertJsonPath('data.aktif', true);

    spa()->actingAs($admin, 'web')
        ->patchJson("/api/admin/divisions/{$divisi->id}", ['aktif' => true])
        ->assertOk()->assertJsonPath('data.aktif', true);
});

it('divisi dapat dinonaktifkan walau dipakai riwayat, tidak ada DELETE (T-28, BR-27)', function () {
    $admin = adminUji();
    $divisi = Division::factory()->create(['nama' => 'Umum']);

    spa()->actingAs($admin, 'web')
        ->patchJson("/api/admin/divisions/{$divisi->id}", ['aktif' => false])
        ->assertOk()->assertJsonPath('data.aktif', false);

    // DELETE tidak tersedia
    spa()->actingAs($admin, 'web')->deleteJson("/api/admin/divisions/{$divisi->id}")->assertStatus(405);
    spa()->actingAs($admin, 'web')->deleteJson("/api/admin/categories/{$divisi->id}")->assertStatus(405);

    expect(Division::query()->where('id', $divisi->id)->where('aktif', false)->exists())->toBeTrue();
});

it('kategori nonaktif hilang dari kiosk dan divisi nonaktif hilang dari kiosk (T-28, T-30)', function () {
    $admin = adminUji();
    $kategori = Category::factory()->create(['nama' => 'Elektronik', 'aktif' => true]);
    $divisi = Division::factory()->create(['nama' => 'Umum', 'aktif' => true]);

    // Sebelum: muncul di kiosk
    expect($this->getJson('/api/kiosk/categories')->json('data'))->toHaveCount(1)
        ->and($this->getJson('/api/kiosk/divisions')->json('data'))->toHaveCount(1);

    spa()->actingAs($admin, 'web')
        ->patchJson("/api/admin/categories/{$kategori->id}", ['aktif' => false])->assertOk();
    spa()->actingAs($admin, 'web')
        ->patchJson("/api/admin/divisions/{$divisi->id}", ['aktif' => false])->assertOk();

    // Sesudah: hilang dari kiosk
    $this->getJson('/api/kiosk/categories')->assertOk()->assertJson(['data' => []]);
    $this->getJson('/api/kiosk/divisions')->assertOk()->assertJson(['data' => []]);
});

it('GalatBisnis membawa kode dan pesan khusus tanpa header khusus', function () {
    $hasil = PenangananGalat::tangani(
        GalatBisnis::konflik('Pesan khusus terlihat.', 'kode_khusus'),
        Request::create('/api/uji', 'GET'),
    );

    expect($hasil->getStatusCode())->toBe(409)
        ->and($hasil->getData(true))->toBe([
            'kode' => 'kode_khusus',
            'pesan' => 'Pesan khusus terlihat.',
        ]);
});
