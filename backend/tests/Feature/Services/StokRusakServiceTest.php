<?php

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StokService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

afterEach(function () {
    assertInvarian();
});

function adminRusak(): User
{
    return User::create([
        'name' => 'Admin Rusak',
        'email' => 'rusak@example.test',
        'password' => 'sandi-uji-panjang-123',
    ]);
}

function itemRusak(int $jumlah, User $admin): Item
{
    $kategori = Category::firstOrCreate(['nama' => 'Uji']);
    $item = Item::create([
        'nama' => 'Laptop',
        'kategori_id' => $kategori->id,
        'total' => 0,
        'tersedia' => 0,
        'rusak' => 0,
    ]);

    app(StokService::class)->tambah($item->id, $jumlah, $admin->id, 'Awal');

    return $item->refresh();
}

function ledgerRusakCocok(Item $item): void
{
    $item->refresh();

    expect((int) StockMovement::where('item_id', $item->id)->sum('delta_total'))->toBe($item->total);
    expect((int) StockMovement::where('item_id', $item->id)->sum('delta_tersedia'))->toBe($item->tersedia);
    expect((int) StockMovement::where('item_id', $item->id)->sum('delta_rusak'))->toBe($item->rusak);
}

it('menandai rusak: tersedia berkurang, rusak bertambah, total tetap (T-33)', function () {
    $admin = adminRusak();
    $item = itemRusak(5, $admin);

    app(StokService::class)->tandaiRusak($item->id, 2, $admin->id, 'Layar pecah');

    $item->refresh();
    expect([$item->total, $item->tersedia, $item->rusak])->toBe([5, 3, 2]);

    $mutasi = StockMovement::where('jenis', 'TANDAI_RUSAK')->firstOrFail();
    expect(StockMovement::where('jenis', 'TANDAI_RUSAK')->count())->toBe(1);
    expect([$mutasi->delta_total, $mutasi->delta_tersedia, $mutasi->delta_rusak])->toBe([0, -2, 2]);
    expect($mutasi->catatan)->toBe('Layar pecah');
    expect(AuditLog::where('aksi', 'tandai_rusak')->where('entitas_id', $item->id)->count())->toBe(1);
    ledgerRusakCocok($item);
});

it('menolak tandai rusak yang tidak sah tanpa mengubah apa pun (T-33)', function (int $jumlah, string $alasan) {
    $admin = adminRusak();
    $item = itemRusak(5, $admin);
    $sebelum = StockMovement::count();

    expect(fn () => app(StokService::class)->tandaiRusak($item->id, $jumlah, $admin->id, $alasan))
        ->toThrow(ValidationException::class);

    $item->refresh();
    expect([$item->total, $item->tersedia, $item->rusak])->toBe([5, 5, 0]);
    expect(StockMovement::count())->toBe($sebelum);
    expect(AuditLog::where('aksi', 'tandai_rusak')->count())->toBe(0);
})->with([
    'nol' => [0, 'Uji'],
    'negatif' => [-1, 'Uji'],
    'melebihi tersedia' => [6, 'Uji'],
    'alasan kosong' => [1, ''],
    'alasan spasi' => [1, '   '],
]);

it('selesai diperbaiki memindahkan rusak ke tersedia (T-15)', function () {
    $admin = adminRusak();
    $item = itemRusak(5, $admin);
    $stok = app(StokService::class);
    $stok->tandaiRusak($item->id, 2, $admin->id, 'Rusak');

    $stok->selesaiPerbaikan($item->id, 1, $admin->id);

    $item->refresh();
    expect([$item->total, $item->tersedia, $item->rusak])->toBe([5, 4, 1]);

    $mutasi = StockMovement::where('jenis', 'PERBAIKAN_SELESAI')->firstOrFail();
    expect([$mutasi->delta_total, $mutasi->delta_tersedia, $mutasi->delta_rusak])->toBe([0, 1, -1]);
    expect(AuditLog::where('aksi', 'selesai_perbaikan')->count())->toBe(1);
    ledgerRusakCocok($item);
});

it('menolak perbaikan yang tidak sah', function (int $jumlah) {
    $admin = adminRusak();
    $item = itemRusak(5, $admin);
    $stok = app(StokService::class);
    $stok->tandaiRusak($item->id, 2, $admin->id, 'Rusak');
    $sebelum = StockMovement::count();

    expect(fn () => $stok->selesaiPerbaikan($item->id, $jumlah, $admin->id))
        ->toThrow(ValidationException::class);

    $item->refresh();
    expect([$item->total, $item->tersedia, $item->rusak])->toBe([5, 3, 2]);
    expect(StockMovement::count())->toBe($sebelum);
})->with([0, -1, 3]);

it('afkir mengurangi rusak dan total, tersedia tidak berubah (T-15)', function () {
    $admin = adminRusak();
    $item = itemRusak(5, $admin);
    $stok = app(StokService::class);
    $stok->tandaiRusak($item->id, 2, $admin->id, 'Rusak');

    $stok->afkir($item->id, 1, $admin->id, 'Tidak bisa diperbaiki');

    $item->refresh();
    expect([$item->total, $item->tersedia, $item->rusak])->toBe([4, 3, 1]);

    $mutasi = StockMovement::where('jenis', 'AFKIR')->firstOrFail();
    expect([$mutasi->delta_total, $mutasi->delta_tersedia, $mutasi->delta_rusak])->toBe([-1, 0, -1]);
    expect($mutasi->catatan)->toBe('Tidak bisa diperbaiki');
    expect(AuditLog::where('aksi', 'afkir')->count())->toBe(1);
    ledgerRusakCocok($item);
});

it('menolak afkir yang tidak sah', function (int $jumlah, string $alasan) {
    $admin = adminRusak();
    $item = itemRusak(5, $admin);
    $stok = app(StokService::class);
    $stok->tandaiRusak($item->id, 2, $admin->id, 'Rusak');
    $sebelum = StockMovement::count();

    expect(fn () => $stok->afkir($item->id, $jumlah, $admin->id, $alasan))
        ->toThrow(ValidationException::class);

    $item->refresh();
    expect([$item->total, $item->tersedia, $item->rusak])->toBe([5, 3, 2]);
    expect(StockMovement::count())->toBe($sebelum);
})->with([
    'nol' => [0, 'Uji'],
    'negatif' => [-1, 'Uji'],
    'melebihi rusak' => [3, 'Uji'],
    'alasan kosong' => [1, ''],
]);

it('rangkaian operasi menjaga buku besar dan keseimbangan stok', function () {
    $admin = adminRusak();
    $item = itemRusak(5, $admin);
    $stok = app(StokService::class);

    $stok->tandaiRusak($item->id, 2, $admin->id, 'Rusak');
    $stok->selesaiPerbaikan($item->id, 1, $admin->id);
    $stok->afkir($item->id, 1, $admin->id, 'Afkir');

    $item->refresh();
    expect([$item->total, $item->tersedia, $item->rusak])->toBe([4, 4, 0]);
    expect($item->tersedia + $item->rusak)->toBeLessThanOrEqual($item->total);
    expect(StockMovement::count())->toBe(4);
    ledgerRusakCocok($item);
});
