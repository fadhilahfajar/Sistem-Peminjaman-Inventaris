<?php

use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('mengembalikan hanya lima parameter publik tanpa login', function () {
    $this->withoutExceptionHandling();
    $this->seed(SettingSeeder::class);

    $r = $this->getJson('/api/kiosk/config');
    dump($r->status(), $r->getContent());
    $r->assertOk();

    expect(array_keys($r->json('data')))->toEqualCanonicalizing([
        'maks_durasi_awal_hari', 'maks_booking_hari', 'maks_perpanjangan',
        'maks_hari_per_perpanjangan', 'kiosk_idle_detik',
    ]);
});

it('tidak membuka parameter keamanan', function () {
    $this->seed(SettingSeeder::class);

    $r = $this->getJson('/api/kiosk/config')->assertOk();
    $json = $r->getContent();

    expect($json)->not->toContain('sesi_idle_menit')
        ->and($json)->not->toContain('kiosk_lookup');
});
