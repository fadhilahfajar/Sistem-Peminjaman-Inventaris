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

it('membuat admin aktif dengan kata sandi ter-hash (T-29)', function () {
    $this->artisan('inventaris:buat-admin', ['username' => 'AdminBaru'])
        ->expectsQuestion('Nama admin', 'Admin Uji')
        ->expectsQuestion('Kata sandi', 'sandi-uji-panjang-123')
        ->expectsQuestion('Ulangi kata sandi', 'sandi-uji-panjang-123')
        ->assertSuccessful();

    $user = User::where('username', 'adminbaru')->firstOrFail();

    expect($user->aktif)->toBeTrue();
    expect($user->name)->toBe('Admin Uji');
    expect($user->password)->not->toBe('sandi-uji-panjang-123');
    expect(Hash::check('sandi-uji-panjang-123', $user->password))->toBeTrue();
});

it('menolak kata sandi kurang dari 12 karakter', function () {
    $this->artisan('inventaris:buat-admin', ['username' => 'adminpendek'])
        ->expectsQuestion('Nama admin', 'A')
        ->expectsQuestion('Kata sandi', 'pendek')
        ->expectsQuestion('Ulangi kata sandi', 'pendek')
        ->assertFailed();

    expect(User::count())->toBe(0);
});

it('menolak konfirmasi kata sandi yang tidak sama', function () {
    $this->artisan('inventaris:buat-admin', ['username' => 'adminbeda'])
        ->expectsQuestion('Nama admin', 'A')
        ->expectsQuestion('Kata sandi', 'sandi-uji-panjang-123')
        ->expectsQuestion('Ulangi kata sandi', 'sandi-beda-panjang-123')
        ->assertFailed();

    expect(User::count())->toBe(0);
});

it('menolak username yang sudah dipakai', function () {
    User::create([
        'name' => 'Lama',
        'username' => 'sama',
        'email' => 'a@example.test',
        'password' => 'sandi-lama-panjang-1',
        'aktif' => true,
    ]);

    $this->artisan('inventaris:buat-admin', ['username' => 'sama'])
        ->expectsQuestion('Nama admin', 'Baru')
        ->expectsQuestion('Kata sandi', 'sandi-baru-panjang-123')
        ->expectsQuestion('Ulangi kata sandi', 'sandi-baru-panjang-123')
        ->assertFailed();

    expect(User::where('username', 'sama')->count())->toBe(1);
});

it('mereset kata sandi, mengganti hash, dan mengakhiri semua sesi akun itu saja (T-29)', function () {
    $admin = User::create([
        'name' => 'A',
        'username' => 'resetadmin',
        'email' => 'a@example.test',
        'password' => 'sandi-lama-panjang-1',
        'aktif' => true,
    ]);
    $lain = User::create([
        'name' => 'B',
        'username' => 'lainadmin',
        'email' => 'b@example.test',
        'password' => 'sandi-lain-panjang-1',
        'aktif' => true,
    ]);

    sisipSesi('sesi-admin-1', $admin->id);
    sisipSesi('sesi-admin-2', $admin->id);
    sisipSesi('sesi-lain', $lain->id);

    $this->artisan('inventaris:reset-password-admin', ['username' => 'resetadmin'])
        ->expectsQuestion('Kata sandi baru', 'sandi-baru-panjang-123')
        ->expectsQuestion('Ulangi kata sandi baru', 'sandi-baru-panjang-123')
        ->assertSuccessful();

    expect(Hash::check('sandi-baru-panjang-123', $admin->fresh()->password))->toBeTrue();
    expect(DB::table('sessions')->where('user_id', $admin->id)->count())->toBe(0);
    expect(DB::table('sessions')->where('user_id', $lain->id)->count())->toBe(1);
});
