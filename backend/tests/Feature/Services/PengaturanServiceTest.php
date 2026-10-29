<?php

use App\Models\Setting;
use App\Services\PengaturanService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('membaca nilai pengaturan sebagai angka', function () {
    Setting::create(['kunci' => 'sesi_idle_menit', 'nilai' => '15']);

    expect(app(PengaturanService::class)->angka('sesi_idle_menit'))->toBe(15);
});

it('menolak kunci yang tidak dikenal', function () {
    app(PengaturanService::class)->angka('kunci_palsu');
})->throws(InvalidArgumentException::class);

it('menolak nilai di luar rentang', function () {
    app(PengaturanService::class)->validasi('sesi_idle_menit', 0);
})->throws(InvalidArgumentException::class);

it('menerima nilai di dalam rentang', function () {
    app(PengaturanService::class)->validasi('sesi_idle_menit', 15);

    expect(true)->toBeTrue();
});
