<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('membuat user tanpa kolom timestamp bawaan Laravel', function () {
    $user = User::create([
        'name' => 'Admin Uji',
        'email' => 'admin@example.test',
        'password' => 'sandi-uji-panjang-123',
        'aktif' => true,
    ]);

    $user = $user->fresh();

    expect($user->aktif)->toBeTrue();
    expect($user->login_terakhir)->toBeNull();
    expect($user->dibuat_pada)->not->toBeNull();
    expect(Hash::check('sandi-uji-panjang-123', $user->password))->toBeTrue();
});

it('tidak membocorkan password saat diubah ke array', function () {
    $user = User::create([
        'name' => 'Admin Uji',
        'email' => 'admin2@example.test',
        'password' => 'sandi-uji-panjang-123',
    ]);

    expect($user->toArray())->not->toHaveKey('password');
});
