<?php

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StokService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function adminStok(): User
{
    return User::create([
        'name' => 'Admin Stok',
        'email' => 'stok@example.test',
        'password' => 'sandi-uji-panjang-123',
    ]);
}

function itemKosong(int $total = 0, int $tersedia = 0, int $rusak = 0): Item
{
    $kategori = Category::firstOrCreate(['nama' => 'Uji']);

    return Item::create([
        'nama' => 'Laptop',
        'kategori_id' => $kategori->id,
        'total' => $total,
        'tersedia' => $tersedia,
        'rusak' => $rusak,
    ]);
}

function ledgerCocok(Item $item): void
{
    $item->refresh();

    expect((int) StockMovement::where('item_id', $item->id)->sum('delta_total'))->toBe($item->total);
    expect((int) StockMovement::where('item_id', $item->id)->sum('delta_tersedia'))->toBe($item->tersedia);
    expect((int) StockMovement::where('item_id', $item->id)->sum('delta_rusak'))->toBe($item->rusak);
}

it('menambah stok, menulis satu mutasi dan satu audit', function () {
    $admin = adminStok();
    $item = itemKosong();

    app(StokService::class)->tambah($item->id, 5, $admin->id, 'Pembelian baru');

    $item->refresh();
    expect([$item->total, $item->tersedia, $item->rusak])->toBe([5, 5, 0]);

    $mutasi = StockMovement::firstOrFail();
    expect(StockMovement::count())->toBe(1);
    expect($mutasi->jenis)->toBe('TAMBAH_STOK');
    expect([$mutasi->delta_total, $mutasi->delta_tersedia, $mutasi->delta_rusak])->toBe([5, 5, 0]);
    expect($mutasi->user_id)->toBe($admin->id);
    expect($mutasi->catatan)->toBe('Pembelian baru');

    expect(AuditLog::where('aksi', 'tambah_stok')->where('entitas', 'items')->where('entitas_id', $item->id)->count())->toBe(1);
    ledgerCocok($item);
});

it('mengurangi stok, menulis satu mutasi dan satu audit', function () {
    $admin = adminStok();
    $item = itemKosong();
    $stok = app(StokService::class);

    $stok->tambah($item->id, 5, $admin->id, 'Awal');
    $stok->kurangi($item->id, 2, $admin->id, 'Dijual');

    $item->refresh();
    expect([$item->total, $item->tersedia, $item->rusak])->toBe([3, 3, 0]);

    $mutasi = StockMovement::where('jenis', 'KURANG_STOK')->firstOrFail();
    expect([$mutasi->delta_total, $mutasi->delta_tersedia, $mutasi->delta_rusak])->toBe([-2, -2, 0]);

    expect(StockMovement::count())->toBe(2);
    expect(AuditLog::where('aksi', 'kurangi_stok')->count())->toBe(1);
    ledgerCocok($item);
});

it('menolak jumlah yang bukan bulat positif tanpa mengubah apa pun', function (int $jumlah) {
    $admin = adminStok();
    $item = itemKosong(5, 5);

    foreach (['tambah', 'kurangi'] as $metode) {
        expect(fn () => app(StokService::class)->{$metode}($item->id, $jumlah, $admin->id, 'Uji'))
            ->toThrow(ValidationException::class);
    }

    $item->refresh();
    expect([$item->total, $item->tersedia])->toBe([5, 5]);
    expect(StockMovement::count())->toBe(0);
    expect(AuditLog::count())->toBe(0);
})->with([0, -1]);

it('menolak alasan kosong', function (string $alasan) {
    $admin = adminStok();
    $item = itemKosong(5, 5);

    foreach (['tambah', 'kurangi'] as $metode) {
        expect(fn () => app(StokService::class)->{$metode}($item->id, 1, $admin->id, $alasan))
            ->toThrow(ValidationException::class);
    }

    expect(StockMovement::count())->toBe(0);
    expect($item->fresh()->total)->toBe(5);
})->with(['kosong' => '', 'spasi' => '   ']);

it('menolak pengurangan yang membuat tersedia negatif walau total mencukupi', function () {
    $admin = adminStok();
    $item = itemKosong(total: 5, tersedia: 2);

    expect(fn () => app(StokService::class)->kurangi($item->id, 3, $admin->id, 'Uji'))
        ->toThrow(ValidationException::class);

    $item->refresh();
    expect([$item->total, $item->tersedia])->toBe([5, 2]);
    expect(StockMovement::count())->toBe(0);
});

it('menolak barang yang sudah dihapus atau tidak ada', function () {
    $admin = adminStok();
    $item = itemKosong(5, 5);
    $item->delete();

    expect(fn () => app(StokService::class)->tambah($item->id, 1, $admin->id, 'Uji'))
        ->toThrow(ModelNotFoundException::class);

    expect(fn () => app(StokService::class)->tambah(999999, 1, $admin->id, 'Uji'))
        ->toThrow(ModelNotFoundException::class);
});
