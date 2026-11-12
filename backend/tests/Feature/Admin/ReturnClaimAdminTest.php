<?php

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Division;
use App\Models\Item;
use App\Models\Loan;
use App\Models\ReturnClaim;
use App\Services\BarangService;
use App\Services\KlaimService;
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

    $this->barang = function (string $nama = 'Kabel HDMI', int $stok = 5) {
        return app(BarangService::class)->buat([
            'nama' => $nama,
            'kategori_id' => Category::factory()->create()->id,
            'jumlah_awal' => $stok,
        ], $this->admin->id);
    };

    $this->pinjam = function (Item $item, int $qty = 2): Loan {
        return app(PeminjamanService::class)->buat([
            'nama_peminjam' => 'Budi Santoso',
            'division_id' => Division::firstOrCreate(['nama' => 'IT'], ['aktif' => true])->id,
            'tipe_peminjam' => 'karyawan',
            'telepon' => '081234567890',
            'tanggal_pinjam' => ($this->tanggal)(0),
            'batas_kembali' => ($this->tanggal)(3),
            'idempotency_key' => (string) Str::uuid(),
            'items' => [['item_id' => $item->id, 'qty' => $qty]],
        ]);
    };

    $this->klaim = fn (Loan $loan, Item $item, string $kondisi, int $qty, ?string $catatan = null) => app(KlaimService::class)->buat([
        'kode_pinjam' => $loan->kode_pinjam,
        'idempotency_key' => (string) Str::uuid(),
        'catatan_peminjam' => $catatan,
        'baris' => [['item_id' => $item->id, $kondisi => $qty]],
    ]);

    $this->payloadVerifikasi = function (ReturnClaim $klaim, array $hasil): array {
        $lines = $klaim->lines()->orderBy('id')->get()->map(fn ($baris, int $i) => array_merge(
            ['line_id' => $baris->id],
            $hasil[$i],
        ))->values()->all();

        return ['lines' => $lines];
    };
});

it('semua rute admin klaim menolak 401 tanpa sesi', function () {
    spa()->getJson('/api/admin/return-claims')->assertStatus(401);
    spa()->getJson('/api/admin/return-claims/1')->assertStatus(401);
    spa()->postJson('/api/admin/return-claims/1/verify', ['lines' => []])->assertStatus(401);
});

it('GET antrean hanya memuat klaim menunggu, terlama di atas, dan mendukung filter status', function () {
    $item = ($this->barang)();
    $loan = ($this->pinjam)($item, 2);
    $pertama = ($this->klaim)($loan, $item, 'baik', 1);

    $loan2 = ($this->pinjam)($item, 2);
    $kedua = ($this->klaim)($loan2, $item, 'baik', 1);

    // Beri waktu berbeda agar urutan jelas.
    $pertama->update(['diklaim_pada' => now()->subHour()]);
    $kedua->update(['diklaim_pada' => now()]);

    $res = spa()->actingAs($this->admin, 'web')->getJson('/api/admin/return-claims')->assertOk();
    $res->assertJsonPath('data.0.id', $pertama->id)
        ->assertJsonPath('data.1.id', $kedua->id)
        ->assertJsonPath('meta.total', 2);

    $res->assertJsonPath('data.0.peminjaman.kode_pinjam', $loan->kode_pinjam)
        ->assertJsonPath('data.0.peminjaman.telepon', '081234567890')
        ->assertJsonPath('data.0.lines.0.kondisi_dilaporkan', 'baik')
        ->assertJsonPath('data.0.lines.0.hasil', null);

    // Setelah diverifikasi klaim keluar dari antrean default dan muncul pada filter.
    $payload = ($this->payloadVerifikasi)($pertama, [['qty_baik' => 1, 'qty_rusak' => 0, 'qty_hilang' => 0, 'qty_ditolak' => 0]]);
    spa()->actingAs($this->admin, 'web')
        ->postJson("/api/admin/return-claims/{$pertama->id}/verify", $payload)->assertOk();

    spa()->actingAs($this->admin, 'web')->getJson('/api/admin/return-claims')
        ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $kedua->id);

    spa()->actingAs($this->admin, 'web')->getJson('/api/admin/return-claims?status=diverifikasi')
        ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $pertama->id);

    spa()->actingAs($this->admin, 'web')->getJson('/api/admin/return-claims?status=ngawur')
        ->assertStatus(422)->assertJsonValidationErrors('status');
});

it('GET detail menampilkan peminjam, batas kembali, hari terlambat, dan isi klaim', function () {
    $item = ($this->barang)();
    $loan = ($this->pinjam)($item, 2);
    $klaim = ($this->klaim)($loan, $item, 'rusak', 1, 'Layar retak');

    $res = spa()->actingAs($this->admin, 'web')
        ->getJson("/api/admin/return-claims/{$klaim->id}")->assertOk();

    $res->assertJsonPath('data.kode_klaim', $klaim->kode_klaim)
        ->assertJsonPath('data.catatan_peminjam', 'Layar retak')
        ->assertJsonPath('data.peminjaman.nama_peminjam', 'Budi Santoso')
        ->assertJsonPath('data.peminjaman.batas_kembali', ($this->tanggal)(3))
        ->assertJsonPath('data.hari_terlambat', 0)
        ->assertJsonPath('data.lines.0.nama', $item->nama)
        ->assertJsonPath('data.lines.0.qty', 1)
        ->assertJsonPath('data.lines.0.hasil', null);

    spa()->actingAs($this->admin, 'web')->getJson('/api/admin/return-claims/999')->assertStatus(404);
});

it('verifikasi sesuai laporan mengembalikan unit ke tersedia dan menutup klaim', function () {
    $item = ($this->barang)(stok: 5);
    $loan = ($this->pinjam)($item, 2);
    $klaim = ($this->klaim)($loan, $item, 'baik', 1);

    $payload = ($this->payloadVerifikasi)($klaim, [['qty_baik' => 1, 'qty_rusak' => 0, 'qty_hilang' => 0, 'qty_ditolak' => 0]]);

    spa()->actingAs($this->admin, 'web')
        ->postJson("/api/admin/return-claims/{$klaim->id}/verify", $payload)->assertOk()
        ->assertJsonPath('data.status', 'diverifikasi')
        ->assertJsonPath('data.lines.0.hasil.qty_baik', 1)
        ->assertJsonPath('data.verifikator.nama', 'Admin');

    $item->refresh();
    $klaim->refresh();
    expect($item->tersedia)->toBe(4)
        ->and($klaim->status)->toBe('diverifikasi')
        ->and($klaim->diverifikasi_oleh)->toBe($this->admin->id)
        ->and($item->stockMovements()->where('jenis', 'VERIFIKASI_BAIK')->count())->toBe(1)
        ->and(AuditLog::where('aksi', 'verifikasi_klaim')->where('entitas_id', $klaim->id)->count())->toBe(1);

    assertInvarian();
});

it('hasil berbeda tanpa catatan admin ditolak 422 tanpa mengubah stok', function () {
    $item = ($this->barang)(stok: 5);
    $loan = ($this->pinjam)($item, 2);
    $klaim = ($this->klaim)($loan, $item, 'baik', 1);

    $payload = ($this->payloadVerifikasi)($klaim, [['qty_baik' => 0, 'qty_rusak' => 1, 'qty_hilang' => 0, 'qty_ditolak' => 0]]);

    spa()->actingAs($this->admin, 'web')
        ->postJson("/api/admin/return-claims/{$klaim->id}/verify", $payload)
        ->assertStatus(422)->assertJsonValidationErrors('catatan_admin');

    $item->refresh();
    $klaim->refresh();
    expect($item->tersedia)->toBe(3)
        ->and($item->rusak)->toBe(0)
        ->and($klaim->status)->toBe('menunggu');

    assertInvarian();
});

it('hasil berbeda dengan catatan admin diproses dan tercatat di audit', function () {
    $item = ($this->barang)(stok: 5);
    $loan = ($this->pinjam)($item, 2);
    $klaim = ($this->klaim)($loan, $item, 'baik', 1);

    $payload = ($this->payloadVerifikasi)($klaim, [['qty_baik' => 0, 'qty_rusak' => 1, 'qty_hilang' => 0, 'qty_ditolak' => 0]]);
    $payload['catatan_admin'] = 'Ternyata rusak saat diperiksa';

    spa()->actingAs($this->admin, 'web')
        ->postJson("/api/admin/return-claims/{$klaim->id}/verify", $payload)->assertOk()
        ->assertJsonPath('data.catatan_admin', 'Ternyata rusak saat diperiksa')
        ->assertJsonPath('data.lines.0.hasil.qty_rusak', 1);

    $item->refresh();
    expect($item->tersedia)->toBe(3)
        ->and($item->rusak)->toBe(1)
        ->and($item->stockMovements()->where('jenis', 'VERIFIKASI_RUSAK')->count())->toBe(1);

    $audit = AuditLog::where('aksi', 'verifikasi_klaim')->where('entitas_id', $klaim->id)->firstOrFail();
    expect($audit->detail['berbeda_dari_laporan'])->toBeTrue();

    assertInvarian();
});

it('verifikasi dua kali ditolak 409 dan tidak mengubah stok lagi', function () {
    $item = ($this->barang)(stok: 5);
    $loan = ($this->pinjam)($item, 2);
    $klaim = ($this->klaim)($loan, $item, 'baik', 1);

    $payload = ($this->payloadVerifikasi)($klaim, [['qty_baik' => 1, 'qty_rusak' => 0, 'qty_hilang' => 0, 'qty_ditolak' => 0]]);

    spa()->actingAs($this->admin, 'web')
        ->postJson("/api/admin/return-claims/{$klaim->id}/verify", $payload)->assertOk();

    spa()->actingAs($this->admin, 'web')
        ->postJson("/api/admin/return-claims/{$klaim->id}/verify", $payload)
        ->assertStatus(409)->assertJsonPath('kode', 'klaim_sudah_diproses');

    $item->refresh();
    expect($item->tersedia)->toBe(4);

    assertInvarian();
});

it('jumlah hasil tidak sama dengan qty baris dan baris tidak lengkap ditolak 422', function () {
    $item = ($this->barang)();
    $loan = ($this->pinjam)($item, 2);
    $klaim = ($this->klaim)($loan, $item, 'baik', 1);

    $payload = ($this->payloadVerifikasi)($klaim, [['qty_baik' => 1, 'qty_rusak' => 1, 'qty_hilang' => 0, 'qty_ditolak' => 0]]);
    $payload['catatan_admin'] = 'Beda';

    spa()->actingAs($this->admin, 'web')
        ->postJson("/api/admin/return-claims/{$klaim->id}/verify", $payload)
        ->assertStatus(422)->assertJsonValidationErrors('lines.'.$klaim->lines()->first()->id);

    // Baris tidak lengkap: line_id tidak dikenal.
    spa()->actingAs($this->admin, 'web')
        ->postJson("/api/admin/return-claims/{$klaim->id}/verify", [
            'lines' => [['line_id' => 999, 'qty_baik' => 1, 'qty_rusak' => 0, 'qty_hilang' => 0, 'qty_ditolak' => 0]],
        ])->assertStatus(422)->assertJsonValidationErrors('lines');

    // Bentuk dasar: qty negatif ditolak Form Request.
    spa()->actingAs($this->admin, 'web')
        ->postJson("/api/admin/return-claims/{$klaim->id}/verify", [
            'lines' => [['line_id' => 1, 'qty_baik' => -1, 'qty_rusak' => 0, 'qty_hilang' => 0, 'qty_ditolak' => 0]],
        ])->assertStatus(422)->assertJsonValidationErrors('lines.0.qty_baik');

    $klaim->refresh();
    expect($klaim->status)->toBe('menunggu');

    assertInvarian();
});

it('klaim tidak ada mengembalikan 404 saat verifikasi', function () {
    spa()->actingAs($this->admin, 'web')->postJson('/api/admin/return-claims/999/verify', [
        'lines' => [['line_id' => 1, 'qty_baik' => 1, 'qty_rusak' => 0, 'qty_hilang' => 0, 'qty_ditolak' => 0]],
    ])->assertStatus(404);
});
