<?php

use App\Models\AuditLog;
use App\Support\Audit;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('mencatat satu baris audit dengan detail', function () {
    Audit::catat('tambah_stok', 'items', 3, null, ['jumlah' => 2]);

    $baris = AuditLog::firstOrFail();

    expect($baris->aksi)->toBe('tambah_stok');
    expect($baris->entitas)->toBe('items');
    expect($baris->entitas_id)->toBe(3);
    expect($baris->detail)->toBe(['jumlah' => 2]);
});

it('menerima detail kosong', function () {
    Audit::catat('uji', 'items', null, null, null);

    expect(AuditLog::count())->toBe(1);
});

it('menolak detail yang memuat kata sandi atau token, termasuk bersarang', function (array $detail) {
    expect(fn () => Audit::catat('uji', 'users', 1, null, $detail))
        ->toThrow(InvalidArgumentException::class);

    expect(AuditLog::count())->toBe(0);
})->with([
    'password' => [['password' => 'rahasia']],
    'token' => [['token' => 'abc']],
    'kata_sandi' => [['kata_sandi' => 'rahasia']],
    'bersarang' => [['data' => ['password' => 'rahasia']]],
]);
