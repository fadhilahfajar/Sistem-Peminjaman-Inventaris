<?php

use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('membatasi laju permintaan pada 120 per menit dan mengembalikan galat 429 seragam', function () {
    $this->seed(SettingSeeder::class);

    // 120 permintaan pertama berhasil
    for ($i = 1; $i <= 120; $i++) {
        $this->getJson('/api/kiosk/config')->assertOk();
    }

    // Permintaan ke-121 ditolak 429 dengan bentuk galat seragam
    $response = $this->getJson('/api/kiosk/config');

    $response->assertStatus(429)
        ->assertJson([
            'kode' => 'terlalu_banyak_percobaan',
            'pesan' => 'Terlalu banyak percobaan. Coba lagi beberapa saat lagi.',
        ])
        ->assertHeader('Retry-After');
});
