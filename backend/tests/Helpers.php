<?php

use App\Models\User;

/** Buat akun admin uji (role web) dengan email unik. */
function adminUji(bool $aktif = true): User
{
    return User::create([
        'name' => 'Admin',
        'email' => uniqid().'@uji.test',
        'password' => 'rahasia-panjang-123',
        'aktif' => $aktif,
    ]);
}

/** Klien SPA: header Origin/Referer frontend dev + sesi kosong. */
function spa()
{
    return test()->withHeaders([
        'Origin' => 'http://localhost:5173',
        'Referer' => 'http://localhost:5173/',
    ])
        ->withSession([]);
}
