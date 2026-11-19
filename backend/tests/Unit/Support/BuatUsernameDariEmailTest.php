<?php

use App\Support\BuatUsernameDariEmail;

it('membuat username dari email biasa', function () {
    expect(BuatUsernameDariEmail::dasar('Admin.User@example.test'))->toBe('admin.user');
});

it('mengganti karakter aneh menjadi garis bawah', function () {
    expect(BuatUsernameDariEmail::dasar('A B+C!#@mail.test'))->toBe('a_b_c_');
});

it('memberi padding bila terlalu pendek', function () {
    expect(BuatUsernameDariEmail::dasar('ab@mail.test'))->toBe('abu');
    expect(BuatUsernameDariEmail::dasar('@mail.test'))->toBe('use');
});

it('memotong bila terlalu panjang', function () {
    $lokal = str_repeat('a', 60);
    $hasil = BuatUsernameDariEmail::dasar($lokal.'@mail.test');

    expect(mb_strlen($hasil))->toBe(50);
    expect($hasil)->toBe(str_repeat('a', 50));
});

it('menambahkan sufiks id saat bentrok', function () {
    $hasil = BuatUsernameDariEmail::dariEmail('admin@example.test', 42, fn ($u) => $u === 'admin');

    expect($hasil)->toBe('admin-42');
});

it('memotong dasar agar hasil bentrok tetap maksimal 50 karakter', function () {
    $lokal = str_repeat('b', 60);
    $hasil = BuatUsernameDariEmail::dariEmail($lokal.'@mail.test', 12345, fn () => true);

    expect(mb_strlen($hasil))->toBeLessThanOrEqual(50);
    expect($hasil)->toEndWith('-12345');
});
