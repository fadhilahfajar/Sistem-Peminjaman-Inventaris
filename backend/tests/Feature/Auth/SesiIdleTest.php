<?php

use App\Http\Middleware\SesiIdle;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SettingSeeder::class);
    CarbonImmutable::setTestNow('2026-10-30 03:00:00');
    $this->admin = User::create([
        'name' => 'Admin', 'email' => 'idle@uji.test',
        'password' => 'rahasia-panjang-123', 'aktif' => true,
    ]);
});

afterEach(fn () => CarbonImmutable::setTestNow());

function meDenganAktivitas(?int $menitLalu)
{
    $sesi = $menitLalu === null
        ? []
        : [SesiIdle::KUNCI => CarbonImmutable::now('UTC')->subMinutes($menitLalu)->timestamp];

    return test()
        ->withHeaders(['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/'])
        ->actingAs(test()->admin, 'web')
        ->withSession($sesi)
        ->getJson('/api/auth/me');
}

it('mengizinkan sesi yang masih dalam batas', function () {
    meDenganAktivitas(5)->assertOk();
});

it('batas tepat 15 menit belum mengakhiri sesi', function () {
    meDenganAktivitas(15)->assertOk();
});

it('mengakhiri sesi yang idle melebihi batas dengan 401', function () {
    meDenganAktivitas(16)->assertStatus(401)->assertJsonPath('kode', 'sesi_berakhir');
});

it('perubahan sesi_idle_menit langsung berlaku', function () {
    DB::table('settings')->where('kunci', 'sesi_idle_menit')->update(['nilai' => '5']);

    meDenganAktivitas(6)->assertStatus(401);
});

it('sesi tanpa catatan aktivitas diizinkan', function () {
    meDenganAktivitas(null)->assertOk();
});
