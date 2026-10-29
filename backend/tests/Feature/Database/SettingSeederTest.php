<?php

use App\Models\Setting;
use App\Services\PengaturanService;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('mengisi delapan kunci dengan nilai bawaan', function () {
    $this->seed(SettingSeeder::class);

    expect(Setting::count())->toBe(8);

    foreach (PengaturanService::ATURAN as $kunci => $aturan) {
        expect(app(PengaturanService::class)->angka($kunci))->toBe($aturan['bawaan']);
    }
});

it('aman dijalankan ulang dan tidak menimpa nilai yang sudah diubah admin', function () {
    $this->seed(SettingSeeder::class);
    Setting::whereKey('sesi_idle_menit')->update(['nilai' => '30']);

    $this->seed(SettingSeeder::class);

    expect(Setting::count())->toBe(8);
    expect(app(PengaturanService::class)->angka('sesi_idle_menit'))->toBe(30);
});
