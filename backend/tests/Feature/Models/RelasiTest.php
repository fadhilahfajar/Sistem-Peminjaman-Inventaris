<?php

use App\Models\Category;
use App\Models\Division;
use App\Models\Item;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\ReturnClaim;
use App\Models\ReturnClaimLine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function rantaiPinjam(string $kode = 'PJM-ABCDEF'): array
{
    $kat = Category::create(['nama' => 'Kabel', 'aktif' => true]);
    $div = Division::create(['nama' => 'IT', 'aktif' => true]);
    $item = Item::create([
        'nama' => 'HDMI', 'kategori_id' => $kat->id,
        'total' => 10, 'tersedia' => 7, 'rusak' => 0, 'boleh_diperpanjang' => true,
    ]);
    $loan = Loan::create([
        'kode_pinjam' => $kode, 'nama_peminjam' => 'Budi', 'division_id' => $div->id,
        'tipe_peminjam' => 'karyawan', 'telepon' => '081234567890',
        'tanggal_pinjam' => '2026-10-28', 'batas_kembali_awal' => '2026-11-04',
        'batas_kembali' => '2026-11-04',
    ]);
    $li = LoanItem::create(['loan_id' => $loan->id, 'item_id' => $item->id, 'qty_dipinjam' => 3]);

    return [$kat, $div, $item, $loan, $li];
}

it('menelusuri rantai dari peminjaman sampai baris klaim', function () {
    [, , , $loan, $li] = rantaiPinjam();
    $klaim = ReturnClaim::create(['kode_klaim' => 'KMB-ABCDEF', 'loan_id' => $loan->id]);
    ReturnClaimLine::create([
        'claim_id' => $klaim->id, 'loan_item_id' => $li->id,
        'kondisi_dilaporkan' => 'baik', 'qty' => 2,
    ]);

    $loan = $loan->fresh();

    expect($loan->divisi->nama)->toBe('IT')
        ->and($loan->items->first()->item->kategori->nama)->toBe('Kabel')
        ->and($loan->claims->first()->lines->first()->loanItem->item->nama)->toBe('HDMI')
        ->and($loan->tanggal_pinjam->format('Y-m-d'))->toBe('2026-10-28')
        ->and($loan->dibuat_pada)->not->toBeNull();
});

it('menyembunyikan telepon dari serialisasi', function () {
    [, , , $loan] = rantaiPinjam();

    expect($loan->fresh()->toArray())->not->toHaveKey('telepon')
        ->and($loan->fresh()->telepon)->toBe('081234567890');
});

it('tetap membaca barang yang sudah di-soft-delete lewat loan item', function () {
    [, , $item, , $li] = rantaiPinjam('PJM-ZZZZZZ');

    $item->delete();

    expect(Item::count())->toBe(0)
        ->and($li->fresh()->item->nama)->toBe('HDMI');
});

it('tidak mengisi nama_aktif lewat mass assignment', function () {
    expect((new Item)->isFillable('nama_aktif'))->toBeFalse();
});
