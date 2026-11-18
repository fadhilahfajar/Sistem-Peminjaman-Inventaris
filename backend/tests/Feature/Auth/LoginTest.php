<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'cache.default' => 'array',
        'session.driver' => 'array',
        'sanctum.stateful' => ['localhost:5173'],
    ]);
    RateLimiter::clear('');
});

function masuk(string $username, string $sandi, string $ip = '127.0.0.1')
{
    return test()->withHeaders([
        'Origin' => 'http://localhost:5173',
        'REMOTE_ADDR' => $ip,
    ])->postJson('/api/auth/login', ['username' => $username, 'password' => $sandi]);
}

function buatAkun(bool $aktif = true): User
{
    return User::create([
        'name' => 'Admin Uji',
        'username' => 'adminuji',
        'email' => 'admin@example.test',
        'password' => 'sandi-uji-panjang-123',
        'aktif' => $aktif,
    ]);
}

it('login dengan username berhasil, huruf besar-kecil tidak berpengaruh, mengisi login_terakhir, dan menulis audit', function () {
    $user = buatAkun();

    masuk('AdminUji', 'sandi-uji-panjang-123')
        ->assertOk()
        ->assertJsonPath('username', 'adminuji');

    expect($user->fresh()->login_terakhir)->not->toBeNull();
    expect(AuditLog::where('user_id', $user->id)->where('aksi', 'login')->count())->toBe(1);
});

it('username tidak ada dan kata sandi salah menghasilkan respons identik', function () {
    buatAkun();

    $salahSandi = masuk('adminuji', 'sandi-salah-panjang-1');
    $tidakAda = masuk('tidakada', 'sandi-uji-panjang-123');

    $salahSandi->assertStatus(422)->assertJsonPath('kode', 'validasi_gagal');
    $tidakAda->assertStatus(422)->assertJsonPath('kode', 'validasi_gagal');

    expect($salahSandi->json('errors.username'))->toBe(['Username atau kata sandi salah.']);
    expect($tidakAda->json('errors.username'))->toBe($salahSandi->json('errors.username'));
    expect($salahSandi->json('pesan'))->toBe($tidakAda->json('pesan'));
});

it('login dengan email lama gagal', function () {
    buatAkun();

    // Coba kredensial lama berbasis email: tidak diterima sebagai username login.
    $res = test()->withHeaders(['Origin' => 'http://localhost:5173'])
        ->postJson('/api/auth/login', ['email' => 'admin@example.test', 'password' => 'sandi-uji-panjang-123']);

    $res->assertStatus(422)->assertJsonPath('kode', 'validasi_gagal');
});

it('akun nonaktif ditolak walau kredensial benar', function () {
    buatAkun(aktif: false);

    masuk('adminuji', 'sandi-uji-panjang-123')->assertForbidden();
});

it('membatasi 5 percobaan gagal lalu 429 per IP dan username, username lain tidak terkunci', function () {
    $user = buatAkun();
    $lain = User::create([
        'name' => 'Admin Lain',
        'username' => 'adminlain',
        'email' => 'lain@example.test',
        'password' => 'sandi-uji-panjang-123',
        'aktif' => true,
    ]);

    foreach (range(1, 5) as $i) {
        masuk('adminuji', 'sandi-salah-panjang-1')->assertStatus(422);
    }

    // Percobaan ke-6 (bahkan dengan sandi benar) ditolak 429 untuk username+IP yang sama.
    masuk('adminuji', 'sandi-uji-panjang-123')->assertStatus(429);
    expect($user->fresh()->login_terakhir)->toBeNull();

    // Username lain pada IP yang sama tidak ikut terkunci.
    masuk('adminlain', 'sandi-uji-panjang-123')->assertOk();
});

it('login tidak menulis kata sandi ke audit_logs', function () {
    buatAkun();

    masuk('adminuji', 'sandi-uji-panjang-123')->assertOk();

    $isi = json_encode(AuditLog::all()->toArray());
    expect($isi)->not->toContain('sandi-uji-panjang-123');
});
