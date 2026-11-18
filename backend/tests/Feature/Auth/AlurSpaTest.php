<?php

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Cookie;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SettingSeeder::class);
    config([
        'session.driver' => 'database',
        'sanctum.stateful' => ['localhost:5173', '127.0.0.1:5173'],
    ]);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function buatAdminSpa(): User
{
    return User::create([
        'name' => 'Admin SPA',
        'email' => 'admin-spa@example.test',
        'password' => 'rahasia-panjang-123',
        'aktif' => true,
    ]);
}

/** Ambil cookie dari TestResponse sebagai objek Symfony Cookie. */
function ambilCookieObjek($response, string $nama): ?Cookie
{
    foreach ($response->headers->getCookies() as $c) {
        if ($c->getName() === $nama) {
            return $c;
        }
    }

    return null;
}

/** Ambil nilai cookie mentah dari TestResponse. */
function ambilNilaiCookie($response, string $nama): ?string
{
    $c = ambilCookieObjek($response, $nama);

    return $c?->getValue();
}

/**
 * Panggil /sanctum/csrf-cookie dan kembalikan token XSRF (terdecode) beserta cookie sesi.
 *
 * @return array{xsrf: string, session: string}
 */
function inisialisasiSesiSpa(): array
{
    $res = test()->withHeaders([
        'Origin' => 'http://localhost:5173',
        'Referer' => 'http://localhost:5173/',
        'Accept' => 'application/json',
    ])->get('/sanctum/csrf-cookie');

    $res->assertStatus(204);

    $xsrfRaw = ambilNilaiCookie($res, 'XSRF-TOKEN');
    $sessionVal = ambilNilaiCookie($res, config('session.cookie'));

    expect($xsrfRaw)->not->toBeNull('Cookie XSRF-TOKEN tidak ditemukan.');
    expect($sessionVal)->not->toBeNull('Cookie sesi tidak ditemukan.');

    return [
        'xsrf' => urldecode($xsrfRaw),
        'session' => $sessionVal,
    ];
}

/**
 * Lakukan login SPA dengan kredensial tertentu.
 *
 * @return array{response: TestResponse, session: string, xsrf: string}
 */
function loginSpa(string $email, string $password): array
{
    $init = inisialisasiSesiSpa();

    $res = test()->withHeaders([
        'Origin' => 'http://localhost:5173',
        'Referer' => 'http://localhost:5173/admin/login',
        'Accept' => 'application/json',
        'X-XSRF-TOKEN' => $init['xsrf'],
    ])->withUnencryptedCookie(config('session.cookie'), $init['session'])
        ->postJson('/api/auth/login', [
            'email' => $email,
            'password' => $password,
        ]);

    $sessionBaru = ambilNilaiCookie($res, config('session.cookie')) ?? $init['session'];
    $xsrfBaru = ambilNilaiCookie($res, 'XSRF-TOKEN');

    return [
        'response' => $res,
        'session' => $sessionBaru,
        'xsrf' => $xsrfBaru ? urldecode($xsrfBaru) : $init['xsrf'],
    ];
}

it('S1: csrf-cookie 204 dan menghasilkan cookie dengan atribut yang tepat', function () {
    $res = $this->withHeaders([
        'Origin' => 'http://localhost:5173',
        'Referer' => 'http://localhost:5173/',
        'Accept' => 'application/json',
    ])->get('/sanctum/csrf-cookie');

    $res->assertStatus(204);

    $namaCookieSesi = config('session.cookie');
    $cookieSesi = ambilCookieObjek($res, $namaCookieSesi);
    $cookieXsrf = ambilCookieObjek($res, 'XSRF-TOKEN');

    expect($cookieXsrf)->not->toBeNull();
    expect($cookieSesi)->not->toBeNull();

    expect($cookieSesi->isHttpOnly())->toBeTrue();
    expect(strtolower((string) $cookieSesi->getSameSite()))->toBe('strict');
    expect($cookieSesi->isSecure())->toBeFalse();
});

it('S2: login benar dengan XSRF dan cookie sukses', function () {
    $admin = buatAdminSpa();

    $hasil = loginSpa($admin->email, 'rahasia-panjang-123');

    $hasil['response']->assertOk()
        ->assertJsonPath('id', $admin->id)
        ->assertJsonPath('email', $admin->email);
    expect($hasil['session'])->not->toBeEmpty();
});

it('S3: segera setelah S2 GET /api/auth/me dan GET /api/admin/summary dengan cookie yang sama 200', function () {
    $admin = buatAdminSpa();
    $login = loginSpa($admin->email, 'rahasia-panjang-123');
    $login['response']->assertOk();

    $cookieSesi = $login['session'];

    $meRes = $this->withHeaders([
        'Origin' => 'http://localhost:5173',
        'Referer' => 'http://localhost:5173/admin/dashboard',
        'Accept' => 'application/json',
    ])->withUnencryptedCookie(config('session.cookie'), $cookieSesi)
        ->getJson('/api/auth/me');

    $meRes->assertOk()
        ->assertJsonPath('data.id', $admin->id)
        ->assertJsonPath('data.email', $admin->email);

    $summaryRes = $this->withHeaders([
        'Origin' => 'http://localhost:5173',
        'Referer' => 'http://localhost:5173/admin/dashboard',
        'Accept' => 'application/json',
    ])->withUnencryptedCookie(config('session.cookie'), $cookieSesi)
        ->getJson('/api/admin/summary');

    $summaryRes->assertOk();
});

it('S4: tabel settings TANPA baris sesi_idle_menit login lalu me tetap 200 (bawaan 15 menit)', function () {
    DB::table('settings')->where('kunci', 'sesi_idle_menit')->delete();
    expect(DB::table('settings')->where('kunci', 'sesi_idle_menit')->exists())->toBeFalse();

    $admin = buatAdminSpa();
    $login = loginSpa($admin->email, 'rahasia-panjang-123');
    $login['response']->assertOk();

    $meRes = $this->withHeaders([
        'Origin' => 'http://localhost:5173',
        'Referer' => 'http://localhost:5173/admin/dashboard',
        'Accept' => 'application/json',
    ])->withUnencryptedCookie(config('session.cookie'), $login['session'])
        ->getJson('/api/auth/me');

    $meRes->assertOk()->assertJsonPath('data.id', $admin->id);
});

it('S5: idle travel +14 menit tetap 200, +16 menit 401 sesi_berakhir, dan aktivitas menyegarkan batas', function () {
    CarbonImmutable::setTestNow('2026-11-18 08:00:00');

    $admin = buatAdminSpa();
    $login = loginSpa($admin->email, 'rahasia-panjang-123');
    $cookieSesi = $login['session'];

    // 14 menit berlalu dari login: sesi masih berlaku (< 15 menit)
    CarbonImmutable::setTestNow('2026-11-18 08:14:00');
    $res14 = $this->withHeaders([
        'Origin' => 'http://localhost:5173',
        'Referer' => 'http://localhost:5173/admin/dashboard',
        'Accept' => 'application/json',
    ])->withUnencryptedCookie(config('session.cookie'), $cookieSesi)
        ->getJson('/api/auth/me');
    $res14->assertOk();

    // Dari aktivitas terakhir di menit 14, maju 16 menit ke menit 30 (> 15 menit idle): 401 sesi_berakhir
    CarbonImmutable::setTestNow('2026-11-18 08:30:00');
    $resExpired = $this->withHeaders([
        'Origin' => 'http://localhost:5173',
        'Referer' => 'http://localhost:5173/admin/dashboard',
        'Accept' => 'application/json',
    ])->withUnencryptedCookie(config('session.cookie'), $cookieSesi)
        ->getJson('/api/auth/me');
    $resExpired->assertStatus(401)
        ->assertJsonPath('kode', 'sesi_berakhir');

    // Uji aktivitas berkala menyegarkan batas:
    // Login baru di jam 09:00:00
    CarbonImmutable::setTestNow('2026-11-18 09:00:00');
    $loginBaru = loginSpa($admin->email, 'rahasia-panjang-123');
    $cookieBaru = $loginBaru['session'];

    // Aktivitas di menit ke-10 (09:10:00)
    CarbonImmutable::setTestNow('2026-11-18 09:10:00');
    $res10 = $this->withHeaders([
        'Origin' => 'http://localhost:5173',
        'Referer' => 'http://localhost:5173/admin/dashboard',
        'Accept' => 'application/json',
    ])->withUnencryptedCookie(config('session.cookie'), $cookieBaru)
        ->getJson('/api/auth/me');
    $res10->assertOk();

    // Aktivitas lagi di menit ke-20 (09:20:00, selisih 10 menit dari aktivitas sebelumnya): tetap 200
    CarbonImmutable::setTestNow('2026-11-18 09:20:00');
    $res20 = $this->withHeaders([
        'Origin' => 'http://localhost:5173',
        'Referer' => 'http://localhost:5173/admin/dashboard',
        'Accept' => 'application/json',
    ])->withUnencryptedCookie(config('session.cookie'), $cookieBaru)
        ->getJson('/api/auth/me');
    $res20->assertOk();
});

it('S6: login dekat tengah malam Asia/Jakarta (UTC 16:55 ke 17:05) me tetap 200', function () {
    // UTC 16:55 = WIB 23:55
    CarbonImmutable::setTestNow('2026-11-18 16:55:00');

    $admin = buatAdminSpa();
    $login = loginSpa($admin->email, 'rahasia-panjang-123');
    $cookieSesi = $login['session'];

    // UTC 17:05 = WIB 00:05 hari berikutnya (selisih 10 menit riil)
    CarbonImmutable::setTestNow('2026-11-18 17:05:00');

    $meRes = $this->withHeaders([
        'Origin' => 'http://localhost:5173',
        'Referer' => 'http://localhost:5173/admin/dashboard',
        'Accept' => 'application/json',
    ])->withUnencryptedCookie(config('session.cookie'), $cookieSesi)
        ->getJson('/api/auth/me');

    $meRes->assertOk()->assertJsonPath('data.id', $admin->id);
});

it('S7: login kredensial salah menghasilkan status dan kode berbeda dari sesi habis dan belum login', function () {
    buatAdminSpa();

    $loginSalah = loginSpa('admin-spa@example.test', 'sandi-keliru-123');

    $loginSalah['response']->assertStatus(422)
        ->assertJsonPath('kode', 'validasi_gagal');

    expect($loginSalah['response']->status())->not->toBe(401);
    expect($loginSalah['response']->json('kode'))->not->toBe('sesi_berakhir');
    expect($loginSalah['response']->json('kode'))->not->toBe('tidak_terautentikasi');
});

it('S8: login tanpa header X-XSRF-TOKEN mengembalikan 419 sesi_kedaluwarsa', function () {
    $admin = buatAdminSpa();
    $init = inisialisasiSesiSpa();

    // PreventRequestForgery melewati pemeriksaan saat env testing; paksa aktif agar 419 teruji.
    $envAsli = $this->app['env'];
    $this->app['env'] = 'local';

    try {
        $res = $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/admin/login',
            'Accept' => 'application/json',
            // Sengaja tanpa X-XSRF-TOKEN
        ])->withUnencryptedCookie(config('session.cookie'), $init['session'])
            ->postJson('/api/auth/login', [
                'email' => $admin->email,
                'password' => 'rahasia-panjang-123',
            ]);

        $res->assertStatus(419)
            ->assertJsonPath('kode', 'sesi_kedaluwarsa');
    } finally {
        $this->app['env'] = $envAsli;
    }
});

it('S9: permintaan dari Origin tidak terdaftar (http://localhost:3000) menghasilkan 401 jelas tanpa 500', function () {
    $admin = buatAdminSpa();
    $login = loginSpa($admin->email, 'rahasia-panjang-123');
    $cookieSesi = $login['session'];

    $res = $this->withHeaders([
        'Origin' => 'http://localhost:3000',
        'Referer' => 'http://localhost:3000/',
        'Accept' => 'application/json',
    ])->withUnencryptedCookie(config('session.cookie'), $cookieSesi)
        ->getJson('/api/auth/me');

    // Catat status sebenarnya; tidak boleh 500.
    expect($res->status())->toBe(401);
    expect($res->status())->not->toBe(500);
    // Origin tidak stateful: sesi tidak dimulai, SesiIdle melaporkan sesi_berakhir (galat jelas, bukan 500).
    expect($res->json('kode'))->toBe('sesi_berakhir');
});

it('S10: me tanpa cookie mengembalikan 401 tidak_terautentikasi (berbeda dari sesi_berakhir)', function () {
    $res = $this->withHeaders([
        'Origin' => 'http://localhost:5173',
        'Referer' => 'http://localhost:5173/admin/dashboard',
        'Accept' => 'application/json',
    ])->getJson('/api/auth/me');

    $res->assertStatus(401)
        ->assertJsonPath('kode', 'tidak_terautentikasi');

    expect($res->json('kode'))->not->toBe('sesi_berakhir');
});

it('S11: dua permintaan bersamaan dengan cookie yang sama tidak saling membatalkan sesi', function () {
    $admin = buatAdminSpa();
    $login = loginSpa($admin->email, 'rahasia-panjang-123');
    $cookieSesi = $login['session'];

    $res1 = $this->withHeaders([
        'Origin' => 'http://localhost:5173',
        'Referer' => 'http://localhost:5173/admin/dashboard',
        'Accept' => 'application/json',
    ])->withUnencryptedCookie(config('session.cookie'), $cookieSesi)
        ->getJson('/api/auth/me');

    $res2 = $this->withHeaders([
        'Origin' => 'http://localhost:5173',
        'Referer' => 'http://localhost:5173/admin/dashboard',
        'Accept' => 'application/json',
    ])->withUnencryptedCookie(config('session.cookie'), $cookieSesi)
        ->getJson('/api/admin/summary');

    $res3 = $this->withHeaders([
        'Origin' => 'http://localhost:5173',
        'Referer' => 'http://localhost:5173/admin/dashboard',
        'Accept' => 'application/json',
    ])->withUnencryptedCookie(config('session.cookie'), $cookieSesi)
        ->getJson('/api/auth/me');

    $res1->assertOk()->assertJsonPath('data.id', $admin->id);
    $res2->assertOk();
    $res3->assertOk()->assertJsonPath('data.id', $admin->id);
});
