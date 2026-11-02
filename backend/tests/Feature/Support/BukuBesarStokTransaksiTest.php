<?php

use App\Models\Category;
use App\Models\Item;
use App\Models\StockMovement;
use App\Support\BukuBesarStok;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

it('menolak menulis buku besar di luar transaksi database', function () {
    $kategori = Category::create(['nama' => 'Uji']);
    $item = Item::create([
        'nama' => 'Laptop',
        'kategori_id' => $kategori->id,
        'total' => 5,
        'tersedia' => 5,
        'rusak' => 0,
    ]);

    expect(fn () => BukuBesarStok::catat($item, 'PINJAM', 0, -1, 0))
        ->toThrow(LogicException::class);

    expect(StockMovement::count())->toBe(0);
});
