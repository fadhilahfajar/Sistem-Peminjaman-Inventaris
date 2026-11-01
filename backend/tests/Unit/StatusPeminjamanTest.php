<?php

use App\Services\StatusPeminjaman as S;

const HARI_INI = '2026-10-30';

function itemStatus(int $dipinjam, int $menunggu = 0, int $terverifikasi = 0): array
{
    return ['qty_dipinjam' => $dipinjam, 'menunggu' => $menunggu, 'terverifikasi' => $terverifikasi];
}

function dataStatus(array $o = []): array
{
    return array_merge([
        'dibatalkan' => false,
        'tanggal_pinjam' => '2026-10-25',
        'batas_kembali' => '2026-11-01',
        'jumlah_perpanjangan' => 0,
        'ada_catatan' => false,
        'items' => [itemStatus(3)],
    ], $o);
}

it('menurunkan status, O, P, dan hari terlambat sesuai DESAIN 4 (T-04)', function (array $data, string $status, int $o, int $p, int $terlambat) {
    $hasil = (new S)->hitung($data, HARI_INI);

    expect($hasil['status'])->toBe($status)
        ->and($hasil['o'])->toBe($o)
        ->and($hasil['p'])->toBe($p)
        ->and($hasil['hari_terlambat'])->toBe($terlambat);
})->with([
    'dibatalkan menang atas dipesan' => [dataStatus(['dibatalkan' => true, 'tanggal_pinjam' => '2026-11-05', 'batas_kembali' => '2026-11-08']), S::DIBATALKAN, 3, 0, 0],
    'tanggal pinjam di masa depan adalah dipesan' => [dataStatus(['tanggal_pinjam' => '2026-11-05', 'batas_kembali' => '2026-11-08']), S::DIPESAN, 3, 0, 0],
    'dipinjam sebelum batas' => [dataStatus(), S::DIPINJAM, 3, 0, 0],
    'tepat di batas belum terlambat' => [dataStatus(['batas_kembali' => '2026-10-30']), S::DIPINJAM, 3, 0, 0],
    'lewat batas tiga hari adalah terlambat' => [dataStatus(['batas_kembali' => '2026-10-27']), S::TERLAMBAT, 3, 0, 3],
    'semua terverifikasi adalah selesai' => [dataStatus(['items' => [itemStatus(3, 0, 3)]]), S::SELESAI, 0, 0, 0],
    'semua diklaim menunggu verifikasi' => [dataStatus(['items' => [itemStatus(3, 3, 0)]]), S::MENUNGGU_VERIFIKASI, 0, 3, 0],
    'menunggu verifikasi tidak terlambat walau lewat batas (D-8)' => [dataStatus(['batas_kembali' => '2026-10-27', 'items' => [itemStatus(3, 3, 0)]]), S::MENUNGGU_VERIFIKASI, 0, 3, 0],
    'sebagian diklaim, sisa masih dipinjam' => [dataStatus(['items' => [itemStatus(3, 2, 0)]]), S::DIPINJAM, 1, 2, 0],
    'unit ditolak kembali menjadi sisa dan terlambat dihitung lagi dari batas kembali' => [dataStatus(['batas_kembali' => '2026-10-27', 'items' => [itemStatus(3, 0, 0)]]), S::TERLAMBAT, 3, 0, 3],
    'dua barang, satu selesai satu bersisa' => [dataStatus(['items' => [itemStatus(2, 0, 2), itemStatus(1, 0, 0)]]), S::DIPINJAM, 1, 0, 0],
    'dua barang, semua sudah diklaim, satu masih menunggu' => [dataStatus(['items' => [itemStatus(2, 0, 2), itemStatus(1, 1, 0)]]), S::MENUNGGU_VERIFIKASI, 0, 1, 0],
]);

it('memberi lencana Sebagian dan Ada klaim menunggu', function () {
    $hasil = (new S)->hitung(dataStatus(['items' => [itemStatus(3, 2, 0)]]), HARI_INI);

    expect($hasil['lencana'])->toBe([S::LENCANA_SEBAGIAN, S::LENCANA_KLAIM_MENUNGGU]);
});

it('memberi lencana Sebagian untuk unit terverifikasi tanpa klaim menunggu', function () {
    $hasil = (new S)->hitung(dataStatus(['items' => [itemStatus(3, 0, 1)]]), HARI_INI);

    expect($hasil['lencana'])->toBe([S::LENCANA_SEBAGIAN]);
});

it('tidak memberi lencana Sebagian bila tidak ada sisa', function () {
    $hasil = (new S)->hitung(dataStatus(['items' => [itemStatus(3, 0, 3)]]), HARI_INI);

    expect($hasil['lencana'])->not->toContain(S::LENCANA_SEBAGIAN);
});

it('memberi lencana Diperpanjang n kali dan Ada catatan', function () {
    $hasil = (new S)->hitung(dataStatus(['jumlah_perpanjangan' => 2, 'ada_catatan' => true]), HARI_INI);

    expect($hasil['lencana'])->toBe([S::LENCANA_DIPERPANJANG, S::LENCANA_CATATAN])
        ->and($hasil['diperpanjang_kali'])->toBe(2);
});

it('tanpa kejadian apa pun tidak ada lencana', function () {
    expect((new S)->hitung(dataStatus(), HARI_INI)['lencana'])->toBe([]);
});

it('menolak data yang melanggar invarian sisa', function () {
    (new S)->hitung(dataStatus(['items' => [itemStatus(3, 2, 2)]]), HARI_INI);
})->throws(LogicException::class);
