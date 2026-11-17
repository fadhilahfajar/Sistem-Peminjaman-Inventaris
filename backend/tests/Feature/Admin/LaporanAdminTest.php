<?php

use App\Models\AuditLog;
use App\Services\LaporanService;
use Carbon\Carbon;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SettingSeeder::class);
    $this->admin = adminUji();
    $this->data = siapkanDataLaporan($this->admin);
});

it('rute laporan menolak 401 tanpa sesi', function () {
    spa()->getJson('/api/admin/reports/rekap.pdf')->assertStatus(401);
    spa()->getJson('/api/admin/reports/belum-kembali.pdf')->assertStatus(401);
});

it('jenis laporan tidak dikenal mengembalikan 404', function () {
    spa()->actingAs($this->admin, 'web')
        ->get('/api/admin/reports/stok.pdf')
        ->assertStatus(404);
});

it('dari lebih besar dari sampai mengembalikan 422', function () {
    spa()->actingAs($this->admin, 'web')
        ->getJson('/api/admin/reports/rekap.pdf?dari=2026-01-02&sampai=2026-01-01')
        ->assertStatus(422)
        ->assertJsonPath('kode', 'validasi_gagal');
});

it('endpoint PDF mengembalikan berkas PDF dengan header yang benar', function () {
    $res = spa()->actingAs($this->admin, 'web')->get('/api/admin/reports/rekap.pdf');

    $res->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Cache-Control', 'no-store');

    expect(str_starts_with($res->getContent(), '%PDF'))->toBeTrue();

    $disposition = $res->headers->get('Content-Disposition');
    expect($disposition)->toMatch('/attachment; filename="laporan-rekap-\d{8}-\d{4}\.pdf"/');
});

it('laporan terlalu besar mengembalikan 422 laporan_terlalu_besar', function () {
    config(['laporan.maks_baris' => 3]);

    spa()->actingAs($this->admin, 'web')
        ->get('/api/admin/reports/rekap.pdf')
        ->assertStatus(422)
        ->assertJsonPath('kode', 'laporan_terlalu_besar');

    config(['laporan.maks_baris' => 2000]);
});

it('render HTML rekap memuat judul, waktu cetak, XSS ter-escape, dan tanpa telepon', function () {
    $data = app(LaporanService::class)->rekap([], $this->admin->name, '2026-11-17 08:30:00');
    $html = view('laporan.rekap', $data)->render();

    expect($html)->toContain('Laporan Rekap Peminjaman')
        ->and($html)->toContain('2026-11-17 08:30:00')
        ->and($html)->toContain('&lt;script&gt;')
        ->and($html)->not->toContain('<script>alert(1)')
        ->and($html)->not->toContain('081234567890')
        ->and($html)->not->toContain('telepon');
});

it('waktu cetak memakai Asia/Jakarta', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-17 20:00:00', 'UTC'));

    $waktu = Carbon::now('Asia/Jakarta')->format('Y-m-d H:i:s');
    $data = app(LaporanService::class)->rekap([], $this->admin->name, $waktu);
    $html = view('laporan.rekap', $data)->render();

    expect($html)->toContain('2026-11-18');

    Carbon::setTestNow();
});

it('audit laporan.diunduh tertulis tanpa data pribadi', function () {
    spa()->actingAs($this->admin, 'web')
        ->get('/api/admin/reports/rekap.pdf')
        ->assertOk();

    $log = AuditLog::where('aksi', 'laporan.diunduh')->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->detail['jenis'])->toBe('rekap')
        ->and($log->detail['jumlah_baris'])->toBe(6);

    $json = json_encode($log->detail);
    expect($json)->not->toContain('081234567890')
        ->and($json)->not->toContain('telepon');
});
