<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function sisipSesi(string $id, int $userId): void
{
    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $userId,
        'payload' => 'x',
        'last_activity' => time(),
    ]);
}

it('membuat admin aktif dengan kata sandi ter-hash', function () {
    $this->artisan('inventaris:buat-admin', ['email' => 'Admin@Example.test', 'nama' => 'Admin Uji'])
        ->expectsQuestion('Kata sandi', 'sandi-uji-panjang-123')
        ->expectsQuestion('Ulangi kata sandi', 'sandi-uji-panjang-123')
        ->assertSuccessful();

    $user = User::where('email', 'admin@example.test')->firstOrFail();

    expect($user->aktif)->toBeTrue();
    expect($user->password)->not->toBe('sandi-uji-panjang-123');
    expect(Hash::check('sandi-uji-panjang-123', $user->password))->toBeTrue();
});

it('menolak kata sandi kurang dari 12 karakter', function () {
    $this->artisan('inventaris:buat-admin', ['email' => 'a@example.test', 'nama' => 'A'])
        ->expectsQuestion('Kata sandi', 'pendek')
        ->expectsQuestion('Ulangi kata sandi', 'pendek')
        ->assertFailed();

    expect(User::count())->toBe(0);
});

it('menolak konfirmasi kata sandi yang tidak sama', function () {
    $this->artisan('inventaris:buat-admin', ['email' => 'a@example.test', 'nama' => 'A'])
        ->expectsQuestion('Kata sandi', 'sandi-uji-panjang-123')
        ->expectsQuestion('Ulangi kata sandi', 'sandi-uji-panjang-124')
        ->assertFailed();

    expect(User::count())->toBe(0);
});

it('menolak email yang sudah dipakai', function () {
    User::create(['name' => 'Lama', 'email' => 'a@example.test', 'password' => 'sandi-lama-panjang-1']);

    $this->artisan('inventaris:buat-admin', ['email' => 'a@example.test', 'nama' => 'Baru'])
        ->expectsQuestion('Kata sandi', 'sandi-uji-panjang-123')
        ->expectsQuestion('Ulangi kata sandi', 'sandi-uji-panjang-123')
        ->assertFailed();

    expect(User::count())->toBe(1);
});

it('reset mengganti hash dan hanya mengakhiri sesi akun itu (T-29)', function () {
    $admin = User::create(['name' => 'A', 'email' => 'a@example.test', 'password' => 'sandi-lama-panjang-1']);
    $lain = User::create(['name' => 'B', 'email' => 'b@example.test', 'password' => 'sandi-lain-panjang-1']);
    sisipSesi('s1', $admin->id);
    sisipSesi('s2', $admin->id);
    sisipSesi('s3', $lain->id);

    $this->artisan('inventaris:reset-password-admin', ['email' => 'a@example.test'])
        ->expectsQuestion('Kata sandi baru', 'sandi-baru-panjang-2')
        ->expectsQuestion('Ulangi kata sandi baru', 'sandi-baru-panjang-2')
        ->assertSuccessful();

    $admin->refresh();

    expect(Hash::check('sandi-baru-panjang-2', $admin->password))->toBeTrue();
    expect(Hash::check('sandi-lama-panjang-1', $admin->password))->toBeFalse();
    expect(DB::table('sessions')->where('user_id', $admin->id)->count())->toBe(0);
    expect(DB::table('sessions')->where('user_id', $lain->id)->count())->toBe(1);
});

it('reset menolak email yang tidak ada', function () {
    $this->artisan('inventaris:reset-password-admin', ['email' => 'tidak-ada@example.test'])
        ->expectsQuestion('Kata sandi baru', 'sandi-baru-panjang-2')
        ->expectsQuestion('Ulangi kata sandi baru', 'sandi-baru-panjang-2')
        ->assertFailed();
});
