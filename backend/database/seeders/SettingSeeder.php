<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Services\PengaturanService;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PengaturanService::ATURAN as $kunci => $aturan) {
            Setting::firstOrCreate(
                ['kunci' => $kunci],
                ['nilai' => (string) $aturan['bawaan']],
            );
        }
    }
}
