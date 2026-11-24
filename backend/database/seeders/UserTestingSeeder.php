<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Akun admin untuk pengujian manual di database dev (bukan produksi).
 * Jalankan eksplisit: php artisan db:seed --class=UserTestingSeeder
 *
 * Idempoten. Kata sandi diambil dari env USER_TESTING_PASSWORD agar tidak
 * tertanam di kode (aturan AGENTS.md no. 12).
 */
class UserTestingSeeder extends Seeder
{
    public function run(): void
    {
        $sandi = (string) env('USER_TESTING_PASSWORD', '');

        if ($sandi === '') {
            $this->command?->warn('USER_TESTING_PASSWORD kosong, UserTestingSeeder dilewati.');

            return;
        }

        User::updateOrCreate(
            ['username' => 'admin-uji'],
            [
                'name' => 'Admin Uji',
                'email' => 'admin-uji@example.com',
                'password' => $sandi,
                'aktif' => true,
            ],
        );
    }
}
