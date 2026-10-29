<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function adminUji(bool $aktif = true): User
{
    return User::create([
        'name' => 'Admin',
        'email' => uniqid().'@uji.test',
        'password' => 'rahasia-panjang-123',
        'aktif' => $aktif,
    ]);
}

function spa()
{
    return test()->withHeaders([
        'Origin' => 'http://localhost:5173',
        'Referer' => 'http://localhost:5173/',
    ])
        ->withSession([]);
}

it('me menolak 401 tanpa sesi (T-01)', function () {
    spa()->getJson('/api/auth/me')->assertStatus(401);
});

it('logout menolak 401 tanpa sesi (T-01)', function () {
    spa()->postJson('/api/auth/logout')->assertStatus(401);
});

it('me mengembalikan data akun tanpa kata sandi', function () {
    $r = spa()->actingAs(adminUji(), 'web')->getJson('/api/auth/me');

    $r->assertOk()->assertJsonStructure(['data' => ['id', 'name', 'email']]);
    expect($r->json('data'))->not->toHaveKey('password');
});

it('logout berhasil untuk akun yang login', function () {
    spa()->actingAs(adminUji(), 'web')->postJson('/api/auth/logout')->assertOk();
});

it('akun yang dinonaktifkan di tengah sesi ditolak 403', function () {
    $user = adminUji();
    $user->update(['aktif' => false]);

    spa()->actingAs($user, 'web')->getJson('/api/auth/me')
        ->assertStatus(403)
        ->assertJsonPath('kode', 'akun_nonaktif');

});
