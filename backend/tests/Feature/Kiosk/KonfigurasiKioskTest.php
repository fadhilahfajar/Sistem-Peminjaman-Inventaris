<?php

use App\Models\Setting;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('mengembalikan hanya lima parameter publik tanpa login', function () {
    $this->withoutExceptionHandling();
    $this->seed(SettingSeeder::class);

    $r = $this->getJson('/api/kiosk/config');
    $r->assertOk();

    expect(array_keys($r->json('data')))->toEqualCanonicalizing([
        'maks_durasi_awal_hari', 'maks_booking_hari', 'maks_perpanjangan',
        'maks_hari_per_perpanjangan', 'kiosk_idle_detik',
    ]);
});

it('mengembalikan nilai yang sesuai dengan pengaturan dan bertipe angka', function () {
    $this->seed(SettingSeeder::class);
    Setting::query()->where('kunci', 'maks_durasi_awal_hari')->update(['nilai' => '14']);

    $r = $this->getJson('/api/kiosk/config')->assertOk();

    expect($r->json('data.maks_durasi_awal_hari'))->toBe(14)
        ->and($r->json('data.maks_booking_hari'))->toBeInt()
        ->and($r->json('data.maks_perpanjangan'))->toBeInt()
        ->and($r->json('data.maks_hari_per_perpanjangan'))->toBeInt()
        ->and($r->json('data.kiosk_idle_detik'))->toBeInt();
});

it('tidak membuka parameter keamanan', function () {
    $this->seed(SettingSeeder::class);

    $r = $this->getJson('/api/kiosk/config')->assertOk();
    $json = $r->getContent();

    expect($json)->not->toContain('sesi_idle_menit')
        ->and($json)->not->toContain('kiosk_lookup');
});
