<?php

use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SettingSeeder::class);
    $this->admin = adminUji();
});

it('semua rute sistem admin menolak 401 tanpa sesi', function () {
    spa()->getJson('/api/admin/summary')->assertStatus(401);
    spa()->getJson('/api/admin/settings')->assertStatus(401);
    spa()->putJson('/api/admin/settings', [])->assertStatus(401);
    spa()->getJson('/api/admin/audit-logs')->assertStatus(401);
    spa()->getJson('/api/admin/users')->assertStatus(401);
    spa()->postJson('/api/admin/users', [])->assertStatus(401);
    spa()->patchJson('/api/admin/users/1', [])->assertStatus(401);
});

it('GET summary memuat angka dasbor dan daftar terlambat', function () {
    $res = spa()->actingAs($this->admin, 'web')->getJson('/api/admin/summary')->assertOk();

    $data = $res->json('data');
    expect(array_keys($data))->toContain('dipinjam', 'terlambat', 'menunggu_verifikasi', 'selesai', 'stok_rusak', 'antrean_verifikasi', 'antrean_perbaikan', 'peminjaman_terlambat')
        ->and($data['peminjaman_terlambat'])->toBeArray();
});

it('GET settings memuat delapan kunci dengan min dan maks; PUT memvalidasi rentang', function () {
    $res = spa()->actingAs($this->admin, 'web')->getJson('/api/admin/settings')->assertOk();
    expect($res->json('data'))->toHaveCount(8);

    spa()->actingAs($this->admin, 'web')->putJson('/api/admin/settings', [
        'sesi_idle_menit' => 0,
    ])->assertStatus(422)->assertJsonValidationErrors('sesi_idle_menit');

    spa()->actingAs($this->admin, 'web')->putJson('/api/admin/settings', [
        'sesi_idle_menit' => 30,
    ])->assertOk();

    $nilai = Setting::query()->whereKey('sesi_idle_menit')->value('nilai');
    expect((int) $nilai)->toBe(30);
});

it('GET audit-logs memuat entri dan mendukung filter aksi', function () {
    AuditLog::create([
        'user_id' => $this->admin->id,
        'aksi' => 'uji.aksi',
        'entitas' => 'uji',
        'entitas_id' => 1,
    ]);

    $res = spa()->actingAs($this->admin, 'web')->getJson('/api/admin/audit-logs')->assertOk();
    expect($res->json('data'))->not->toBeEmpty();

    $filtered = spa()->actingAs($this->admin, 'web')
        ->getJson('/api/admin/audit-logs?aksi=uji.aksi')->assertOk();
    expect($filtered->json('data'))->toHaveCount(1)
        ->and($filtered->json('data.0.aksi'))->toBe('uji.aksi');
});

it('POST users membuat admin, menolak email ganda dan kata sandi pendek', function () {
    $res = spa()->actingAs($this->admin, 'web')->postJson('/api/admin/users', [
        'name' => 'Admin Baru',
        'email' => 'baru@uji.test',
        'password' => 'rahasia-panjang-123',
    ])->assertStatus(201);

    expect($res->json('data.email'))->toBe('baru@uji.test')
        ->and($res->json('data.aktif'))->toBeTrue();

    spa()->actingAs($this->admin, 'web')->postJson('/api/admin/users', [
        'name' => 'Duplikat',
        'email' => 'baru@uji.test',
        'password' => 'rahasia-panjang-123',
    ])->assertStatus(422)->assertJsonValidationErrors('email');

    spa()->actingAs($this->admin, 'web')->postJson('/api/admin/users', [
        'name' => 'Pendek',
        'email' => 'pendek@uji.test',
        'password' => 'pendek',
    ])->assertStatus(422)->assertJsonValidationErrors('password');
});

it('PATCH users menonaktifkan admin lain dan menolak menonaktifkan diri sendiri', function () {
    $lain = User::create([
        'name' => 'Admin Lain',
        'email' => 'lain@uji.test',
        'password' => 'rahasia-panjang-123',
        'aktif' => true,
    ]);

    spa()->actingAs($this->admin, 'web')->patchJson("/api/admin/users/{$lain->id}", [
        'aktif' => false,
    ])->assertOk()->assertJsonPath('data.aktif', false);

    $lain->refresh();
    expect($lain->aktif)->toBeFalse();

    spa()->actingAs($this->admin, 'web')->patchJson("/api/admin/users/{$this->admin->id}", [
        'aktif' => false,
    ])->assertStatus(409)->assertJsonPath('kode', 'tidak_dapat_menonaktifkan_diri');
});
