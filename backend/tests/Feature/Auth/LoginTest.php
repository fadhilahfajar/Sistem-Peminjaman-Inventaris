<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'cache.default' => 'array',
        'session.driver' => 'array',
        'sanctum.stateful' => ['localhost:5173'],
    ]);
});

function masuk(string $email, string $sandi)
{
    return test()->withHeaders(['Origin' => 'http://localhost:5173'])
        ->postJson('/api/auth/login', ['email' => $email, 'password' => $sandi]);
}

function buatAkun(bool $aktif = true): User
{
    return User::create([
        'name' => 'Admin Uji',
        'email' => 'admin@example.test',
        'password' => 'sandi-uji-panjang-123',
        'aktif' => $aktif,
    ]);
}

it('masuk dengan kredensial benar, mengisi login_terakhir, dan menulis audit', function () {
    $user = buatAkun();

    masuk('admin@example.test', 'sandi-uji-panjang-123')->assertOk();

    expect($user->fresh()->login_terakhir)->not->toBeNull();
    expect(AuditLog::where('user_id', $user->id)->where('aksi', 'login')->count())->toBe(1);
});

it('kata sandi salah dan email tidak ada menghasilkan respons yang sama', function () {
    buatAkun();

    $salahSandi = masuk('admin@example.test', 'sandi-salah-panjang-1');
    $tidakAda = masuk('tidak-ada@example.test', 'sandi-uji-panjang-123');

    $salahSandi->assertStatus(422);
    $tidakAda->assertStatus(422);
    expect($salahSandi->json('errors'))->toBe($tidakAda->json('errors'));
});

it('akun nonaktif ditolak walau kredensial benar', function () {
    buatAkun(aktif: false);

    masuk('admin@example.test', 'sandi-uji-panjang-123')->assertForbidden();
});

it('membatasi 5 percobaan gagal per IP dan email', function () {
    $user = buatAkun();

    foreach (range(1, 5) as $i) {
        masuk('admin@example.test', 'sandi-salah-panjang-1')->assertStatus(422);
    }

    masuk('admin@example.test', 'sandi-uji-panjang-123')->assertStatus(429);
    expect($user->fresh()->login_terakhir)->toBeNull();
});

it('login tidak menulis kata sandi ke audit_logs', function () {
    buatAkun();

    masuk('admin@example.test', 'sandi-uji-panjang-123')->assertOk();

    $isi = json_encode(AuditLog::all()->toArray());
    expect($isi)->not->toContain('sandi-uji-panjang-123');
});
