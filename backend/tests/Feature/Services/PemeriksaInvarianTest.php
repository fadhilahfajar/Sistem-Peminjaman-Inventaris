<?php

use App\Models\Category;
use App\Models\Division;
use App\Models\Item;
use App\Models\Loan;
use App\Services\PemeriksaInvarian;
use App\Support\BukuBesarStok;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\AssertionFailedError;

uses(RefreshDatabase::class);

// Tes ini merusak data lewat SQL langsung agar terbukti pemeriksa tidak selalu menjawab "sehat".
// Pengecualian aturan 4 AGENTS.md (sama seperti ConstraintTest), tanyakan A.

function i12Item(int $total): Item
{
    $kat = Category::firstOrCreate(['nama' => 'Uji'], ['aktif' => true]);
    $item = Item::create([
        'nama' => 'Barang '.uniqid(), 'kategori_id' => $kat->id,
        'total' => $total, 'tersedia' => $total, 'rusak' => 0, 'boleh_diperpanjang' => true,
    ]);
    BukuBesarStok::catat($item, 'TAMBAH_STOK', $total, $total, 0, 'items', $item->id);

    return $item;
}

function i12Pinjam(Item $item, int $qty): Loan
{
    $loan = Loan::create([
        'kode_pinjam' => 'PJM-'.strtoupper(substr(md5(uniqid()), 0, 6)),
        'nama_peminjam' => 'Budi',
        'division_id' => Division::firstOrCreate(['nama' => 'IT'], ['aktif' => true])->id,
        'tipe_peminjam' => 'karyawan', 'tanggal_pinjam' => '2026-10-20',
        'batas_kembali_awal' => '2026-11-30', 'batas_kembali' => '2026-11-30',
    ]);
    $loan->items()->create(['item_id' => $item->id, 'qty_dipinjam' => $qty]);
    $item->decrement('tersedia', $qty);
    BukuBesarStok::catat($item, 'PINJAM', 0, -$qty, 0, 'loans', $loan->id);

    return $loan;
}

it('melaporkan daftar kosong dan exit 0 untuk keadaan sehat (T-42)', function () {
    $item = i12Item(10);
    i12Pinjam($item, 3);

    expect((new PemeriksaInvarian)->periksa())->toBe([]);
    $this->artisan('inventaris:periksa-invarian')->assertExitCode(0);
    assertInvarian();
});

it('mendeteksi BR-01 yang dilanggar walau buku besar cocok dan exit non-nol', function () {
    $item = i12Item(10);
    i12Pinjam($item, 3);
    DB::table('loan_items')->where('item_id', $item->id)->update(['qty_dipinjam' => 5]);

    $pelanggaran = (new PemeriksaInvarian)->periksa();

    expect($pelanggaran)->toHaveCount(1)
        ->and($pelanggaran[0])->toContain('BR-01');
    $this->artisan('inventaris:periksa-invarian')->assertExitCode(1);
});

it('mendeteksi angka items yang tidak sama dengan buku besar', function () {
    $item = i12Item(10);
    DB::table('items')->where('id', $item->id)->update(['total' => 11]);

    $teks = implode("\n", (new PemeriksaInvarian)->periksa());

    expect($teks)->toContain('BR-01')->toContain('buku besar');
});

it('mode tanpa buku hanya memeriksa BR-01', function () {
    $kat = Category::firstOrCreate(['nama' => 'Uji'], ['aktif' => true]);
    Item::create(['nama' => 'Tanpa Buku', 'kategori_id' => $kat->id, 'total' => 5, 'tersedia' => 5, 'rusak' => 0, 'boleh_diperpanjang' => true]);

    expect((new PemeriksaInvarian)->periksa(false))->toBe([])
        ->and((new PemeriksaInvarian)->periksa(true))->not->toBe([]);
    assertInvarian(false);
});

it('assertInvarian menggagalkan tes bila ada pelanggaran', function () {
    $item = i12Item(10);
    DB::table('items')->where('id', $item->id)->update(['tersedia' => 9]);

    expect(fn () => assertInvarian())->toThrow(AssertionFailedError::class);
});
