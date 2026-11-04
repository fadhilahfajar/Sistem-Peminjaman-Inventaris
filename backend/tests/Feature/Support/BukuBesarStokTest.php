<?php

use App\Models\Category;
use App\Models\Item;
use App\Models\StockMovement;
use App\Support\BukuBesarStok;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function itemBukuBesar(): Item
{
    $kategori = Category::create(['nama' => 'Uji']);

    return Item::create([
        'nama' => 'Laptop',
        'kategori_id' => $kategori->id,
        'total' => 5,
        'tersedia' => 5,
        'rusak' => 0,
    ]);
}

dataset('mutasi_sah', [
    'TAMBAH_STOK' => ['TAMBAH_STOK', 3, 3, 0],
    'KURANG_STOK' => ['KURANG_STOK', -2, -2, 0],
    'PINJAM' => ['PINJAM', 0, -2, 0],
    'TANDAI_RUSAK' => ['TANDAI_RUSAK', 0, -2, 2],
    'BATAL_PINJAM' => ['BATAL_PINJAM', 0, 2, 0],
    'VERIFIKASI_BAIK' => ['VERIFIKASI_BAIK', 0, 2, 0],
    'VERIFIKASI_RUSAK' => ['VERIFIKASI_RUSAK', 0, 0, 2],
    'VERIFIKASI_HILANG' => ['VERIFIKASI_HILANG', -2, 0, 0],
    'PERBAIKAN_SELESAI' => ['PERBAIKAN_SELESAI', 0, 2, -2],
    'AFKIR' => ['AFKIR', -2, 0, -2],
    'KOREKSI_PINJAM naik' => ['KOREKSI_PINJAM', 0, 1, 0],
    'KOREKSI_PINJAM turun' => ['KOREKSI_PINJAM', 0, -1, 0],
]);

dataset('mutasi_salah', [
    'PINJAM menambah tersedia' => ['PINJAM', 0, 2, 0],
    'PINJAM mengubah total' => ['PINJAM', -2, -2, 0],
    'TANDAI_RUSAK tidak seimbang' => ['TANDAI_RUSAK', 0, -2, 1],
    'TAMBAH_STOK total beda tersedia' => ['TAMBAH_STOK', 3, 2, 0],
    'VERIFIKASI_HILANG menambah' => ['VERIFIKASI_HILANG', 2, 0, 0],
    'AFKIR tidak seimbang' => ['AFKIR', -2, 0, -1],
    'PERBAIKAN_SELESAI terbalik' => ['PERBAIKAN_SELESAI', 0, -2, 2],
    'KOREKSI_PINJAM nol' => ['KOREKSI_PINJAM', 0, 0, 0],
]);

it('mencatat tepat satu baris untuk mutasi yang sesuai tabel efek', function (string $jenis, int $dt, int $dtersedia, int $dr) {
    $item = itemBukuBesar();

    $mutasi = BukuBesarStok::catat(
        item: $item,
        jenis: $jenis,
        deltaTotal: $dt,
        deltaTersedia: $dtersedia,
        deltaRusak: $dr,
        refTipe: 'loans',
        refId: 7,
        catatan: 'uji',
    );

    expect(StockMovement::count())->toBe(1);
    expect($mutasi->item_id)->toBe($item->id);
    expect($mutasi->jenis)->toBe($jenis);
    expect([$mutasi->delta_total, $mutasi->delta_tersedia, $mutasi->delta_rusak])->toBe([$dt, $dtersedia, $dr]);
    expect($mutasi->ref_tipe)->toBe('loans');
    expect($mutasi->ref_id)->toBe(7);
})->with('mutasi_sah');

it('menolak mutasi yang tidak sesuai tabel efek dan tidak menulis apa pun', function (string $jenis, int $dt, int $dtersedia, int $dr) {
    $item = itemBukuBesar();

    expect(fn () => BukuBesarStok::catat($item, $jenis, $dt, $dtersedia, $dr))
        ->toThrow(InvalidArgumentException::class);

    expect(StockMovement::count())->toBe(0);
})->with('mutasi_salah');

it('menolak jenis yang tidak dikenal', function () {
    $item = itemBukuBesar();

    expect(fn () => BukuBesarStok::catat($item, 'SALAH', 0, 1, 0))
        ->toThrow(InvalidArgumentException::class);

    expect(StockMovement::count())->toBe(0);
});
