<?php

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Division;
use App\Models\Item;
use App\Models\StockMovement;
use App\Services\BarangService;
use App\Services\PeminjamanService;
use App\Support\Tanggal;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SettingSeeder::class);
});

it('semua rute admin barang dan stok menolak 401 tanpa sesi', function () {
    spa()->getJson('/api/admin/items')->assertStatus(401);
    spa()->postJson('/api/admin/items')->assertStatus(401);
    spa()->getJson('/api/admin/items/1')->assertStatus(401);
    spa()->putJson('/api/admin/items/1')->assertStatus(401);
    spa()->deleteJson('/api/admin/items/1')->assertStatus(401);
    spa()->postJson('/api/admin/items/1/stock-adjustments')->assertStatus(401);
    spa()->postJson('/api/admin/items/1/mark-damaged')->assertStatus(401);
    spa()->postJson('/api/admin/items/1/repair-complete')->assertStatus(401);
    spa()->postJson('/api/admin/items/1/write-off')->assertStatus(401);
    spa()->getJson('/api/admin/repairs')->assertStatus(401);
});

it('POST /api/admin/items membuat barang dengan jumlah_awal n dan mencatat TAMBAH_STOK', function () {
    $admin = adminUji();
    $kategori = Category::factory()->create(['nama' => 'Komputer']);

    $res = spa()->actingAs($admin, 'web')->postJson('/api/admin/items', [
        'nama' => 'Laptop ThinkPad T14',
        'deskripsi' => 'Laptop kerja standar IT',
        'kategori_id' => $kategori->id,
        'boleh_diperpanjang' => true,
        'jumlah_awal' => 5,
    ])->assertCreated();

    $res->assertJsonPath('data.nama', 'Laptop ThinkPad T14')
        ->assertJsonPath('data.kategori.id', $kategori->id)
        ->assertJsonPath('data.kategori.nama', 'Komputer')
        ->assertJsonPath('data.total', 5)
        ->assertJsonPath('data.tersedia', 5)
        ->assertJsonPath('data.rusak', 0)
        ->assertJsonPath('data.sedang_keluar', 0)
        ->assertJsonPath('data.kondisi', 'baik')
        ->assertJsonPath('data.boleh_diperpanjang', true);

    $itemId = $res->json('data.id');
    $item = Item::findOrFail($itemId);
    expect($item->total)->toBe(5)
        ->and($item->tersedia)->toBe(5)
        ->and($item->rusak)->toBe(0);

    // Tepat satu baris stock_movements TAMBAH_STOK
    $mutasi = StockMovement::where('item_id', $itemId)->get();
    expect($mutasi)->toHaveCount(1)
        ->and($mutasi->first()->jenis)->toBe('TAMBAH_STOK')
        ->and($mutasi->first()->delta_total)->toBe(5)
        ->and($mutasi->first()->delta_tersedia)->toBe(5)
        ->and($mutasi->first()->delta_rusak)->toBe(0);

    assertInvarian();
});

it('POST /api/admin/items membuat barang dengan jumlah_awal 0 tanpa mutasi stok', function () {
    $admin = adminUji();
    $kategori = Category::factory()->create();

    $res = spa()->actingAs($admin, 'web')->postJson('/api/admin/items', [
        'nama' => 'Kabel HDMI 2M',
        'kategori_id' => $kategori->id,
        'jumlah_awal' => 0,
    ])->assertCreated();

    $res->assertJsonPath('data.total', 0)
        ->assertJsonPath('data.tersedia', 0)
        ->assertJsonPath('data.rusak', 0)
        ->assertJsonPath('data.sedang_keluar', 0)
        ->assertJsonPath('data.kondisi', 'baik');

    $itemId = $res->json('data.id');
    expect(StockMovement::where('item_id', $itemId)->count())->toBe(0);

    assertInvarian();
});

it('menolak nama ganda di antara barang aktif tetapi membolehkan nama barang terhapus', function () {
    $admin = adminUji();
    $kategori = Category::factory()->create();

    spa()->actingAs($admin, 'web')->postJson('/api/admin/items', [
        'nama' => 'Proyektor Epson',
        'kategori_id' => $kategori->id,
        'jumlah_awal' => 0,
    ])->assertCreated();

    // Nama ganda ditolak 422
    spa()->actingAs($admin, 'web')->postJson('/api/admin/items', [
        'nama' => 'Proyektor Epson',
        'kategori_id' => $kategori->id,
        'jumlah_awal' => 0,
    ])->assertStatus(422)->assertJsonValidationErrors('nama');

    // Case-insensitive check
    spa()->actingAs($admin, 'web')->postJson('/api/admin/items', [
        'nama' => 'PROYEKTOR EPSON',
        'kategori_id' => $kategori->id,
        'jumlah_awal' => 0,
    ])->assertStatus(422)->assertJsonValidationErrors('nama');

    // Soft-delete barang pertama
    $item = Item::where('nama', 'Proyektor Epson')->first();
    spa()->actingAs($admin, 'web')->deleteJson("/api/admin/items/{$item->id}")->assertOk();

    // Sekarang boleh membuat barang baru dengan nama yang sama
    spa()->actingAs($admin, 'web')->postJson('/api/admin/items', [
        'nama' => 'Proyektor Epson',
        'kategori_id' => $kategori->id,
        'jumlah_awal' => 0,
    ])->assertCreated();
});

it('menolak kategori nonaktif pada POST dan PUT (BR-28)', function () {
    $admin = adminUji();
    $kategoriNonaktif = Category::factory()->nonaktif()->create();
    $kategoriAktif = Category::factory()->create();

    // POST ditolak
    spa()->actingAs($admin, 'web')->postJson('/api/admin/items', [
        'nama' => 'Barang Baru',
        'kategori_id' => $kategoriNonaktif->id,
    ])->assertStatus(422)->assertJsonValidationErrors('kategori_id');

    // Buat barang valid
    $item = app(BarangService::class)->buat([
        'nama' => 'Barang Uji',
        'kategori_id' => $kategoriAktif->id,
    ], $admin->id);

    // PUT dengan kategori nonaktif ditolak
    spa()->actingAs($admin, 'web')->putJson("/api/admin/items/{$item->id}", [
        'nama' => 'Barang Uji',
        'kategori_id' => $kategoriNonaktif->id,
        'boleh_diperpanjang' => true,
    ])->assertStatus(422)->assertJsonValidationErrors('kategori_id');
});

it('menolak field total, tersedia, dan rusak di PUT dengan pesan gunakan penyesuaian stok', function () {
    $admin = adminUji();
    $kategori = Category::factory()->create();
    $item = app(BarangService::class)->buat([
        'nama' => 'Barang Stok',
        'kategori_id' => $kategori->id,
    ], $admin->id);

    $res = spa()->actingAs($admin, 'web')->putJson("/api/admin/items/{$item->id}", [
        'nama' => 'Barang Stok Diubah',
        'kategori_id' => $kategori->id,
        'boleh_diperpanjang' => false,
        'total' => 10,
    ])->assertStatus(422);

    $res->assertJsonValidationErrors(['total']);
    expect($res->json('errors.total.0'))->toBe('Gunakan penyesuaian stok.');

    spa()->actingAs($admin, 'web')->putJson("/api/admin/items/{$item->id}", [
        'nama' => 'Barang Stok Diubah',
        'kategori_id' => $kategori->id,
        'boleh_diperpanjang' => false,
        'tersedia' => 5,
    ])->assertStatus(422)->assertJsonValidationErrors(['tersedia']);

    spa()->actingAs($admin, 'web')->putJson("/api/admin/items/{$item->id}", [
        'nama' => 'Barang Stok Diubah',
        'kategori_id' => $kategori->id,
        'boleh_diperpanjang' => false,
        'rusak' => 2,
    ])->assertStatus(422)->assertJsonValidationErrors(['rusak']);
});

it('hapus bersih melakukan soft-delete dan barang terhapus mengembalikan 404 (T-05)', function () {
    $admin = adminUji();
    $kategori = Category::factory()->create();
    $item = app(BarangService::class)->buat([
        'nama' => 'Barang Bersih',
        'kategori_id' => $kategori->id,
        'jumlah_awal' => 0,
    ], $admin->id);

    spa()->actingAs($admin, 'web')->deleteJson("/api/admin/items/{$item->id}")
        ->assertOk()
        ->assertJsonPath('pesan', 'Barang berhasil dihapus.');

    expect(Item::find($item->id))->toBeNull()
        ->and(Item::withTrashed()->find($item->id)->dihapus_pada)->not->toBeNull();

    // GET, PUT, DELETE barang terhapus menghasilkan 404
    spa()->actingAs($admin, 'web')->getJson("/api/admin/items/{$item->id}")->assertStatus(404);
    spa()->actingAs($admin, 'web')->putJson("/api/admin/items/{$item->id}", [
        'nama' => 'Barang Terhapus',
        'kategori_id' => $kategori->id,
        'boleh_diperpanjang' => true,
    ])->assertStatus(404);
    spa()->actingAs($admin, 'web')->deleteJson("/api/admin/items/{$item->id}")->assertStatus(404);
});

it('hapus saat ada unit dipinjam ditolak 409 (BR-14)', function () {
    $admin = adminUji();
    $kategori = Category::factory()->create();
    $divisi = Division::firstOrCreate(['nama' => 'IT'], ['aktif' => true]);

    $item = app(BarangService::class)->buat([
        'nama' => 'Laptop Dipinjam',
        'kategori_id' => $kategori->id,
        'jumlah_awal' => 3,
    ], $admin->id);

    // Buat peminjaman lewat PeminjamanService
    app(PeminjamanService::class)->buat([
        'nama_peminjam' => 'Andi',
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

    $res = spa()->actingAs($admin, 'web')->deleteJson("/api/admin/items/{$item->id}")
        ->assertStatus(409);

    $res->assertJsonPath('kode', 'barang_masih_dipinjam');
    expect($res->json('pesan'))->toContain('1 unit');

    expect(Item::find($item->id))->not->toBeNull();
});

it('hapus saat rusak > 0 ditolak 409 (BR-14)', function () {
    $admin = adminUji();
    $kategori = Category::factory()->create();

    $item = app(BarangService::class)->buat([
        'nama' => 'Printer Rusak',
        'kategori_id' => $kategori->id,
        'jumlah_awal' => 3,
    ], $admin->id);

    // Tandai rusak 1 unit
    spa()->actingAs($admin, 'web')->postJson("/api/admin/items/{$item->id}/mark-damaged", [
        'jumlah' => 1,
        'alasan' => 'Macet kertas parah',
    ])->assertOk();

    $res = spa()->actingAs($admin, 'web')->deleteJson("/api/admin/items/{$item->id}")
        ->assertStatus(409);

    $res->assertJsonPath('kode', 'barang_masih_rusak');
    expect($res->json('pesan'))->toContain('1 unit rusak');

    expect(Item::find($item->id))->not->toBeNull();
});

it('hapus saat kategori nonaktif ditolak 409 (BR-33)', function () {
    $admin = adminUji();
    $kategori = Category::factory()->create();

    $item = app(BarangService::class)->buat([
        'nama' => 'Barang Kategori Mati',
        'kategori_id' => $kategori->id,
        'jumlah_awal' => 0,
    ], $admin->id);

    // Nonaktifkan kategori
    $kategori->update(['aktif' => false]);

    $res = spa()->actingAs($admin, 'web')->deleteJson("/api/admin/items/{$item->id}")
        ->assertStatus(409);

    $res->assertJsonPath('kode', 'kategori_nonaktif');
    expect(Item::find($item->id))->not->toBeNull();
});

it('tambah dan kurang stok sukses dengan assertInvarian, kurang melebihi tersedia ditolak 422', function () {
    $admin = adminUji();
    $kategori = Category::factory()->create();

    $item = app(BarangService::class)->buat([
        'nama' => 'Switch Hub',
        'kategori_id' => $kategori->id,
        'jumlah_awal' => 0,
    ], $admin->id);

    // Tambah stok 10
    $resTambah = spa()->actingAs($admin, 'web')->postJson("/api/admin/items/{$item->id}/stock-adjustments", [
        'arah' => 'tambah',
        'jumlah' => 10,
        'alasan' => 'Pembelian batch 1',
    ])->assertOk();

    $resTambah->assertJsonPath('data.total', 10)
        ->assertJsonPath('data.tersedia', 10);
    assertInvarian();

    // Kurang stok 4
    $resKurang = spa()->actingAs($admin, 'web')->postJson("/api/admin/items/{$item->id}/stock-adjustments", [
        'arah' => 'kurang',
        'jumlah' => 4,
        'alasan' => 'Koreksi kelebihan catat',
    ])->assertOk();

    $resKurang->assertJsonPath('data.total', 6)
        ->assertJsonPath('data.tersedia', 6);
    assertInvarian();

    // Kurang stok melebihi tersedia (100) ditolak 422 tanpa mengubah stok
    spa()->actingAs($admin, 'web')->postJson("/api/admin/items/{$item->id}/stock-adjustments", [
        'arah' => 'kurang',
        'jumlah' => 100,
        'alasan' => 'Kelebihan',
    ])->assertStatus(422)->assertJsonValidationErrors('jumlah');

    $item->refresh();
    expect($item->total)->toBe(6)
        ->and($item->tersedia)->toBe(6);
    assertInvarian();
});

it('mark-damaged valid dan menolak nol, negatif, pecahan, atau melebihi tersedia (T-33)', function () {
    $admin = adminUji();
    $kategori = Category::factory()->create();

    $item = app(BarangService::class)->buat([
        'nama' => 'Monitor Dell',
        'kategori_id' => $kategori->id,
        'jumlah_awal' => 5,
    ], $admin->id);

    // Valid: tandai 2 unit rusak
    $res = spa()->actingAs($admin, 'web')->postJson("/api/admin/items/{$item->id}/mark-damaged", [
        'jumlah' => 2,
        'alasan' => 'Panel bergaris vertikal',
    ])->assertOk();

    $res->assertJsonPath('data.total', 5)
        ->assertJsonPath('data.tersedia', 3)
        ->assertJsonPath('data.rusak', 2)
        ->assertJsonPath('data.kondisi', 'sebagian_rusak');

    $mutasi = StockMovement::where('item_id', $item->id)->where('jenis', 'TANDAI_RUSAK')->get();
    expect($mutasi)->toHaveCount(1)
        ->and($mutasi->first()->delta_tersedia)->toBe(-2)
        ->and($mutasi->first()->delta_rusak)->toBe(2)
        ->and($mutasi->first()->delta_total)->toBe(0);

    assertInvarian();

    // Uji penolakan angka tidak sah
    // Nol
    spa()->actingAs($admin, 'web')->postJson("/api/admin/items/{$item->id}/mark-damaged", [
        'jumlah' => 0,
        'alasan' => 'Uji nol',
    ])->assertStatus(422)->assertJsonValidationErrors('jumlah');

    // Negatif
    spa()->actingAs($admin, 'web')->postJson("/api/admin/items/{$item->id}/mark-damaged", [
        'jumlah' => -1,
        'alasan' => 'Uji negatif',
    ])->assertStatus(422)->assertJsonValidationErrors('jumlah');

    // Pecahan
    spa()->actingAs($admin, 'web')->postJson("/api/admin/items/{$item->id}/mark-damaged", [
        'jumlah' => 1.5,
        'alasan' => 'Uji pecahan',
    ])->assertStatus(422)->assertJsonValidationErrors('jumlah');

    // Melebihi tersedia (tersedia saat ini 3)
    spa()->actingAs($admin, 'web')->postJson("/api/admin/items/{$item->id}/mark-damaged", [
        'jumlah' => 4,
        'alasan' => 'Uji melebihi',
    ])->assertStatus(422)->assertJsonValidationErrors('jumlah');

    // Pastikan angka tidak berubah
    $item->refresh();
    expect($item->total)->toBe(5)
        ->and($item->tersedia)->toBe(3)
        ->and($item->rusak)->toBe(2);

    assertInvarian();
});

it('repair-complete dan write-off sesuai BR-09 (T-15)', function () {
    $admin = adminUji();
    $kategori = Category::factory()->create();

    $item = app(BarangService::class)->buat([
        'nama' => 'Keyboard Mechanical',
        'kategori_id' => $kategori->id,
        'jumlah_awal' => 4,
    ], $admin->id);

    // Tandai 3 unit rusak
    spa()->actingAs($admin, 'web')->postJson("/api/admin/items/{$item->id}/mark-damaged", [
        'jumlah' => 3,
        'alasan' => 'Switch mati',
    ])->assertOk();

    // Perbaikan selesai 1 unit: rusak berkurang, tersedia bertambah, total tetap
    $resRepair = spa()->actingAs($admin, 'web')->postJson("/api/admin/items/{$item->id}/repair-complete", [
        'jumlah' => 1,
        'catatan' => 'Solder ulang switch selesai',
    ])->assertOk();

    $resRepair->assertJsonPath('data.total', 4)
        ->assertJsonPath('data.tersedia', 2)
        ->assertJsonPath('data.rusak', 2);
    assertInvarian();

    // Afkir 1 unit: rusak berkurang, total berkurang, tersedia tetap
    $resWriteOff = spa()->actingAs($admin, 'web')->postJson("/api/admin/items/{$item->id}/write-off", [
        'jumlah' => 1,
        'alasan' => 'PCB patah total',
    ])->assertOk();

    $resWriteOff->assertJsonPath('data.total', 3)
        ->assertJsonPath('data.tersedia', 2)
        ->assertJsonPath('data.rusak', 1);
    assertInvarian();
});

it('GET /api/admin/repairs hanya memuat barang dengan rusak > 0, urut rusak desc lalu nama', function () {
    $admin = adminUji();
    $kategori = Category::factory()->create();

    $itemA = app(BarangService::class)->buat(['nama' => 'Barang B', 'kategori_id' => $kategori->id, 'jumlah_awal' => 5], $admin->id);
    $itemB = app(BarangService::class)->buat(['nama' => 'Barang A', 'kategori_id' => $kategori->id, 'jumlah_awal' => 5], $admin->id);
    $itemC = app(BarangService::class)->buat(['nama' => 'Barang C Sehat', 'kategori_id' => $kategori->id, 'jumlah_awal' => 5], $admin->id);

    // Tandai rusak: A (2 unit), B (2 unit), C (0 unit)
    spa()->actingAs($admin, 'web')->postJson("/api/admin/items/{$itemA->id}/mark-damaged", ['jumlah' => 2, 'alasan' => 'rusak'])->assertOk();
    spa()->actingAs($admin, 'web')->postJson("/api/admin/items/{$itemB->id}/mark-damaged", ['jumlah' => 2, 'alasan' => 'rusak'])->assertOk();

    $res = spa()->actingAs($admin, 'web')->getJson('/api/admin/repairs')->assertOk();
    $data = $res->json('data');

    // Hanya 2 barang, urut rusak desc lalu nama asc: Barang A dulu baru Barang B
    expect($data)->toHaveCount(2)
        ->and($data[0]['nama'])->toBe('Barang A')
        ->and($data[1]['nama'])->toBe('Barang B')
        ->and(array_keys($data[0]))->toEqualCanonicalizing(['id', 'nama', 'kategori', 'rusak', 'tersedia', 'total']);
});

it('kondisi diturunkan dengan benar untuk ketiga keadaan (T-31)', function () {
    $admin = adminUji();
    $kategori = Category::factory()->create();

    // Keadaan 1: Baik (rusak = 0)
    $item = app(BarangService::class)->buat(['nama' => 'Item Kondisi', 'kategori_id' => $kategori->id, 'jumlah_awal' => 4], $admin->id);
    $res1 = spa()->actingAs($admin, 'web')->getJson("/api/admin/items/{$item->id}")->assertOk();
    $res1->assertJsonPath('data.kondisi', 'baik');

    // Keadaan 2: Sebagian rusak (0 < rusak < total)
    spa()->actingAs($admin, 'web')->postJson("/api/admin/items/{$item->id}/mark-damaged", ['jumlah' => 1, 'alasan' => 'rusak 1'])->assertOk();
    $res2 = spa()->actingAs($admin, 'web')->getJson("/api/admin/items/{$item->id}")->assertOk();
    $res2->assertJsonPath('data.kondisi', 'sebagian_rusak');

    // Keadaan 3: Semua rusak (rusak = total dan total > 0)
    spa()->actingAs($admin, 'web')->postJson("/api/admin/items/{$item->id}/mark-damaged", ['jumlah' => 3, 'alasan' => 'rusak semua'])->assertOk();
    $res3 = spa()->actingAs($admin, 'web')->getJson("/api/admin/items/{$item->id}")->assertOk();
    $res3->assertJsonPath('data.kondisi', 'semua_rusak');
});

it('nama berisi <script>alert(1)</script> tersimpan dan dikembalikan apa adanya di JSON (T-35)', function () {
    $admin = adminUji();
    $kategori = Category::factory()->create();
    $namaXss = '<script>alert(1)</script>';

    $res = spa()->actingAs($admin, 'web')->postJson('/api/admin/items', [
        'nama' => $namaXss,
        'kategori_id' => $kategori->id,
        'jumlah_awal' => 0,
    ])->assertCreated();

    $res->assertJsonPath('data.nama', $namaXss);

    $id = $res->json('data.id');
    $resGet = spa()->actingAs($admin, 'web')->getJson("/api/admin/items/{$id}")->assertOk();
    $resGet->assertJsonPath('data.nama', $namaXss);

    $item = Item::findOrFail($id);
    expect($item->nama)->toBe($namaXss);
});

it('audit tertulis untuk operasi buat, ubah, dan hapus barang', function () {
    $admin = adminUji();
    $kategori = Category::factory()->create();

    // 1. Buat
    $resBuat = spa()->actingAs($admin, 'web')->postJson('/api/admin/items', [
        'nama' => 'Barang Audit',
        'kategori_id' => $kategori->id,
        'jumlah_awal' => 2,
    ])->assertCreated();

    $id = $resBuat->json('data.id');
    $auditBuat = AuditLog::where('aksi', 'barang.dibuat')
        ->where('entitas', 'items')
        ->where('entitas_id', $id)
        ->first();
    expect($auditBuat)->not->toBeNull()
        ->and($auditBuat->user_id)->toBe($admin->id)
        ->and($auditBuat->detail['nama'])->toBe('Barang Audit');

    // 2. Ubah
    spa()->actingAs($admin, 'web')->putJson("/api/admin/items/{$id}", [
        'nama' => 'Barang Audit Diubah',
        'kategori_id' => $kategori->id,
        'boleh_diperpanjang' => false,
    ])->assertOk();

    $auditUbah = AuditLog::where('aksi', 'barang.diubah')
        ->where('entitas', 'items')
        ->where('entitas_id', $id)
        ->first();
    expect($auditUbah)->not->toBeNull()
        ->and($auditUbah->user_id)->toBe($admin->id)
        ->and($auditUbah->detail['sesudah']['nama'])->toBe('Barang Audit Diubah');

    // Buat stok 0 dulu sebelum hapus
    spa()->actingAs($admin, 'web')->postJson("/api/admin/items/{$id}/stock-adjustments", [
        'arah' => 'kurang',
        'jumlah' => 2,
        'alasan' => 'Dikosongkan sebelum hapus',
    ])->assertOk();

    // 3. Hapus
    spa()->actingAs($admin, 'web')->deleteJson("/api/admin/items/{$id}")->assertOk();

    $auditHapus = AuditLog::where('aksi', 'barang.dihapus')
        ->where('entitas', 'items')
        ->where('entitas_id', $id)
        ->first();
    expect($auditHapus)->not->toBeNull()
        ->and($auditHapus->user_id)->toBe($admin->id);
});

it('GET /api/admin/items mendukung pencarian wildcard LIKE dan filter kategori dengan paginasi', function () {
    $admin = adminUji();
    $kat1 = Category::factory()->create(['nama' => 'Kategori 1']);
    $kat2 = Category::factory()->create(['nama' => 'Kategori 2']);

    app(BarangService::class)->buat(['nama' => 'Laptop Dell 100% Baru', 'kategori_id' => $kat1->id], $admin->id);
    app(BarangService::class)->buat(['nama' => 'Laptop HP _Model', 'kategori_id' => $kat1->id], $admin->id);
    app(BarangService::class)->buat(['nama' => 'Mouse Wireless', 'kategori_id' => $kat2->id], $admin->id);

    // Cari dengan karakter khusus wildcard %
    $resWildcard1 = spa()->actingAs($admin, 'web')->getJson('/api/admin/items?q=%25')->assertOk();
    expect($resWildcard1->json('data'))->toHaveCount(1)
        ->and($resWildcard1->json('data.0.nama'))->toBe('Laptop Dell 100% Baru');

    // Cari dengan karakter khusus wildcard _
    $resWildcard2 = spa()->actingAs($admin, 'web')->getJson('/api/admin/items?q=_')->assertOk();
    expect($resWildcard2->json('data'))->toHaveCount(1)
        ->and($resWildcard2->json('data.0.nama'))->toBe('Laptop HP _Model');

    // Filter kategori
    $resKat = spa()->actingAs($admin, 'web')->getJson("/api/admin/items?kategori={$kat2->id}")->assertOk();
    expect($resKat->json('data'))->toHaveCount(1)
        ->and($resKat->json('data.0.nama'))->toBe('Mouse Wireless')
        ->and($resKat->json('meta.total'))->toBe(1);
});
