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

/** Ambil semua kunci (rekursif) dari struktur JSON/array. */
function kunciJsonRekursif(array $data): array
{
    $hasil = [];
    foreach ($data as $kunci => $nilai) {
        if (is_string($kunci)) {
            $hasil[] = $kunci;
        }
        if (is_array($nilai)) {
            array_push($hasil, ...kunciJsonRekursif($nilai));
        }
    }

    return $hasil;
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
