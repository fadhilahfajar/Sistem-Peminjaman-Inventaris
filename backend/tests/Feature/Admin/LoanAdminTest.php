<?php

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Division;
use App\Models\Item;
use App\Models\Loan;
use App\Services\BarangService;
use App\Services\KlaimService;
use App\Services\PembatalanService;
use App\Services\PeminjamanService;
use App\Support\Tanggal;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SettingSeeder::class);
    $this->admin = adminUji();

    $this->tanggal = fn (int $offset = 0): string => (new DateTimeImmutable(
        Tanggal::hariIni(),
        new DateTimeZone('Asia/Jakarta'),
    ))->modify("{$offset} days")->format('Y-m-d');

    // Data awal tes lewat Service, bukan mengisi kolom stok langsung.
    $this->barang = function (string $nama = 'Kabel HDMI', int $stok = 10, ?Category $kategori = null) {
        $kategori ??= Category::factory()->create();

        return app(BarangService::class)->buat([
            'nama' => $nama,
            'kategori_id' => $kategori->id,
            'jumlah_awal' => $stok,
        ], $this->admin->id);
    };

    $this->pinjam = function (Item $item, int $qty = 2, array $override = []): Loan {
        return app(PeminjamanService::class)->buat(array_merge([
            'nama_peminjam' => 'Budi Santoso',
            'division_id' => Division::firstOrCreate(['nama' => 'IT'], ['aktif' => true])->id,
            'tipe_peminjam' => 'karyawan',
            'telepon' => '081234567890',
            'tanggal_pinjam' => ($this->tanggal)(0),
            'batas_kembali' => ($this->tanggal)(3),
            'idempotency_key' => (string) Str::uuid(),
            'items' => [['item_id' => $item->id, 'qty' => $qty]],
        ], $override));
    };

    $this->klaim = fn (Loan $loan, Item $item, array $baris, ?string $catatan = null) => app(KlaimService::class)->buat([
        'kode_pinjam' => $loan->kode_pinjam,
        'idempotency_key' => (string) Str::uuid(),
        'catatan_peminjam' => $catatan,
        'baris' => [$baris + ['item_id' => $item->id]],
    ]);
});

it('semua rute admin peminjaman menolak 401 tanpa sesi', function () {
    spa()->getJson('/api/admin/loans')->assertStatus(401);
    spa()->getJson('/api/admin/loans/1')->assertStatus(401);
    spa()->patchJson('/api/admin/loans/1', ['alasan' => 'x'])->assertStatus(401);
    spa()->postJson('/api/admin/loans/1/extensions', ['tambahan_hari' => 1, 'alasan' => 'x'])->assertStatus(401);
    spa()->postJson('/api/admin/loans/1/cancel', ['alasan' => 'x'])->assertStatus(401);
});

it('GET /api/admin/loans memuat telepon, status turunan, dan paginasi', function () {
    $item = ($this->barang)();
    $loan = ($this->pinjam)($item, 2);

    $res = spa()->actingAs($this->admin, 'web')->getJson('/api/admin/loans')->assertOk();

    $res->assertJsonPath('data.0.id', $loan->id)
        ->assertJsonPath('data.0.telepon', '081234567890')
        ->assertJsonPath('data.0.status', 'dipinjam')
        ->assertJsonPath('data.0.jumlah_unit', 2)
        ->assertJsonPath('data.0.divisi.nama', 'IT')
        ->assertJsonPath('meta.total', 1);
});

it('filter status membedakan dipesan, dipinjam, terlambat, selesai, dan dibatalkan', function () {
    $item = ($this->barang)();

    $dipesan = ($this->pinjam)($item, 1, ['tanggal_pinjam' => ($this->tanggal)(2), 'batas_kembali' => ($this->tanggal)(5)]);
    $dipinjam = ($this->pinjam)($item, 1);
    $dibatalkan = ($this->pinjam)($item, 1);
    app(PembatalanService::class)->olehAdmin($dibatalkan->id, $this->admin->id, 'Salah input');

    spa()->actingAs($this->admin, 'web')->getJson('/api/admin/loans?status=dipesan')
        ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $dipesan->id);

    spa()->actingAs($this->admin, 'web')->getJson('/api/admin/loans?status=dibatalkan')
        ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $dibatalkan->id);

    // Peminjaman yang masih berjalan diubah tanggalnya ke masa lalu agar terlambat.
    $dipinjam->update([
        'tanggal_pinjam' => ($this->tanggal)(-10),
        'batas_kembali' => ($this->tanggal)(-7),
    ]);

    spa()->actingAs($this->admin, 'web')->getJson('/api/admin/loans?status=terlambat')
        ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $dipinjam->id);

    // Status tidak sah ditolak 422.
    spa()->actingAs($this->admin, 'web')->getJson('/api/admin/loans?status=ngawur')
        ->assertStatus(422)->assertJsonValidationErrors('status');
});

it('filter barang, kategori, divisi, tipe, teks, dan belum_kembali bekerja', function () {
    $kategoriA = Category::factory()->create(['nama' => 'Komputer']);
    $kategoriB = Category::factory()->create(['nama' => 'Audio']);
    $itemA = ($this->barang)('Laptop', 5, $kategoriA);
    $itemB = ($this->barang)('Speaker', 5, $kategoriB);

    $divisiLain = Division::firstOrCreate(['nama' => 'Media'], ['aktif' => true]);
    ($this->pinjam)($itemA, 1, ['nama_peminjam' => 'Andi Wijaya']);
    $pinjamB = ($this->pinjam)($itemB, 1, [
        'nama_peminjam' => 'Siti Aminah',
        'tipe_peminjam' => 'magang',
        'division_id' => $divisiLain->id,
    ]);

    $cari = fn (string $query) => spa()->actingAs($this->admin, 'web')
        ->getJson('/api/admin/loans?'.$query)->assertOk()->json('data');

    expect($cari('barang='.$itemA->id))->toHaveCount(1)
        ->and($cari('kategori='.$kategoriB->id)[0]['id'])->toBe($pinjamB->id)
        ->and($cari('divisi='.$divisiLain->id))->toHaveCount(1)
        ->and($cari('tipe_peminjam=magang')[0]['nama_peminjam'])->toBe('Siti Aminah')
        ->and($cari('q=Andi'))->toHaveCount(1)
        ->and($cari('belum_kembali=1'))->toHaveCount(2);
});

it('filter ada_catatan dan ada_kerusakan memakai isi klaim', function () {
    $item = ($this->barang)();
    $denganCatatan = ($this->pinjam)($item, 1);
    ($this->pinjam)($item, 1);

    ($this->klaim)($denganCatatan, $item, ['baik' => 0, 'rusak' => 1, 'hilang' => 0], 'Layar retak');

    $catatan = spa()->actingAs($this->admin, 'web')
        ->getJson('/api/admin/loans?ada_catatan=1')->assertOk()->json('data');
    $rusak = spa()->actingAs($this->admin, 'web')
        ->getJson('/api/admin/loans?ada_kerusakan=1')->assertOk()->json('data');

    expect($catatan)->toHaveCount(1)
        ->and($catatan[0]['id'])->toBe($denganCatatan->id)
        ->and($rusak)->toHaveCount(1)
        ->and($rusak[0]['id'])->toBe($denganCatatan->id);
});

it('GET /api/admin/loans/{id} menampilkan detail dan lini masa', function () {
    $item = ($this->barang)();
    $loan = ($this->pinjam)($item, 2);
    ($this->klaim)($loan, $item, ['baik' => 1, 'rusak' => 0, 'hilang' => 0], 'Kembali sebagian');

    $res = spa()->actingAs($this->admin, 'web')
        ->getJson("/api/admin/loans/{$loan->id}")->assertOk();

    $res->assertJsonPath('data.kode_pinjam', $loan->kode_pinjam)
        ->assertJsonPath('data.items.0.qty_dipinjam', 2)
        ->assertJsonPath('data.items.0.menunggu', 1)
        ->assertJsonPath('data.items.0.sisa', 1)
        ->assertJsonPath('data.klaim.0.catatan_peminjam', 'Kembali sebagian');

    $jenis = collect($res->json('data.lini_masa'))->pluck('jenis')->all();
    expect($jenis)->toContain('dibuat')->toContain('klaim');

    spa()->actingAs($this->admin, 'web')->getJson('/api/admin/loans/999')->assertStatus(404);
});

it('PATCH koreksi mengubah header, menulis riwayat, dan audit', function () {
    $item = ($this->barang)();
    $loan = ($this->pinjam)($item, 2);

    $baru = ($this->tanggal)(5);

    spa()->actingAs($this->admin, 'web')->patchJson("/api/admin/loans/{$loan->id}", [
        'batas_kembali' => $baru,
        'telepon' => '081200000001',
        'alasan' => 'Salah tanggal',
    ])->assertOk()
        ->assertJsonPath('data.batas_kembali', $baru)
        ->assertJsonPath('data.telepon', '081200000001');

    $loan->refresh();
    expect($loan->batas_kembali->format('Y-m-d'))->toBe($baru)
        ->and($loan->telepon)->toBe('081200000001')
        ->and($loan->corrections()->where('kolom', 'batas_kembali')->count())->toBe(1)
        ->and(AuditLog::where('aksi', 'koreksi_peminjaman')->where('entitas_id', $loan->id)->count())->toBe(1);

    assertInvarian();
});

it('koreksi tanpa alasan ditolak 422 dan koreksi jumlah saat sudah ada klaim ditolak 409', function () {
    $item = ($this->barang)();
    $loan = ($this->pinjam)($item, 2);

    spa()->actingAs($this->admin, 'web')->patchJson("/api/admin/loans/{$loan->id}", [
        'batas_kembali' => ($this->tanggal)(4),
    ])->assertStatus(422)->assertJsonValidationErrors('alasan');

    ($this->klaim)($loan, $item, ['baik' => 1, 'rusak' => 0, 'hilang' => 0]);

    spa()->actingAs($this->admin, 'web')->patchJson("/api/admin/loans/{$loan->id}", [
        'items' => [['item_id' => $item->id, 'qty' => 1]],
        'alasan' => 'Kurangi jumlah',
    ])->assertStatus(409)->assertJsonPath('kode', 'barang_sudah_memiliki_klaim');

    assertInvarian();
});

it('koreksi jumlah memperbarui stok dan buku besar KOREKSI_PINJAM', function () {
    $item = ($this->barang)();
    $loan = ($this->pinjam)($item, 2);

    spa()->actingAs($this->admin, 'web')->patchJson("/api/admin/loans/{$loan->id}", [
        'items' => [['item_id' => $item->id, 'qty' => 3]],
        'alasan' => 'Tambah satu unit',
    ])->assertOk();

    $item->refresh();
    expect($item->tersedia)->toBe(7)
        ->and($loan->items()->first()->qty_dipinjam)->toBe(3)
        ->and($item->stockMovements()->where('jenis', 'KOREKSI_PINJAM')->count())->toBe(1);

    assertInvarian();
});

it('admin memperpanjang peminjaman terlambat tanpa batas kuota', function () {
    $item = ($this->barang)();
    $loan = ($this->pinjam)($item, 1);
    $batasLama = $loan->batas_kembali->format('Y-m-d');

    $this->travel(10)->days();

    spa()->actingAs($this->admin, 'web')->postJson("/api/admin/loans/{$loan->id}/extensions", [
        'tambahan_hari' => 2,
        'alasan' => 'Peminjam masih butuh',
    ])->assertOk();

    $loan->refresh();
    $ext = $loan->extensions()->latest('id')->first();
    expect($loan->batas_kembali->format('Y-m-d'))->toBe(
        (new DateTimeImmutable($batasLama))->modify('+2 days')->format('Y-m-d'),
    )->and($ext->diajukan_oleh)->toBe('admin')
        ->and($ext->user_id)->toBe($this->admin->id);
});

it('perpanjangan admin tanpa alasan ditolak 422', function () {
    $item = ($this->barang)();
    $loan = ($this->pinjam)($item, 1);

    spa()->actingAs($this->admin, 'web')->postJson("/api/admin/loans/{$loan->id}/extensions", [
        'tambahan_hari' => 2,
    ])->assertStatus(422)->assertJsonValidationErrors('alasan');
});

it('admin membatalkan peminjaman sebelum klaim dan stok kembali', function () {
    $item = ($this->barang)();
    $loan = ($this->pinjam)($item, 2);

    spa()->actingAs($this->admin, 'web')->postJson("/api/admin/loans/{$loan->id}/cancel", [
        'alasan' => 'Barang dibutuhkan mendesak',
    ])->assertOk()->assertJsonPath('data.status', 'dibatalkan');

    $loan->refresh();
    $item->refresh();
    expect($loan->dibatalkan_pada)->not->toBeNull()
        ->and($loan->alasan_batal)->toBe('Barang dibutuhkan mendesak')
        ->and($loan->dibatalkan_oleh)->toBe($this->admin->id)
        ->and($item->tersedia)->toBe(10);

    assertInvarian();
});

it('pembatalan admin setelah ada klaim ditolak 409', function () {
    $item = ($this->barang)();
    $loan = ($this->pinjam)($item, 1);
    ($this->klaim)($loan, $item, ['baik' => 1, 'rusak' => 0, 'hilang' => 0]);

    spa()->actingAs($this->admin, 'web')->postJson("/api/admin/loans/{$loan->id}/cancel", [
        'alasan' => 'Batal',
    ])->assertStatus(409)->assertJsonPath('kode', 'peminjaman_memiliki_klaim');
});

it('page_size di atas 100 dan rentang tanggal terbalik ditolak 422', function () {
    spa()->actingAs($this->admin, 'web')->getJson('/api/admin/loans?page_size=101')
        ->assertStatus(422)->assertJsonValidationErrors('page_size');

    spa()->actingAs($this->admin, 'web')->getJson('/api/admin/loans?dari=2026-11-10&sampai=2026-11-01')
        ->assertStatus(422)->assertJsonValidationErrors('sampai');
});
