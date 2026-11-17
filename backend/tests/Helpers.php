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

/** Data statis untuk smoke test templat PDF laporan. */
function dataUjiPdfDasar(): array
{
    return [
        'judul' => 'Laporan Uji Cetak',
        'periode' => '2026-11-01 s.d. 2026-11-17',
        'waktuCetak' => (new DateTimeImmutable('2026-11-17 08:30:00', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d H:i:s'),
        'baris' => [
            ['nama' => 'Laptop é', 'catatan' => 'Amat & aman', 'jumlah' => 12],
            ['nama' => 'Proyektor', 'catatan' => "<script>alert('x')</script> > 1", 'jumlah' => 3],
        ],
    ];
}
