<?php

use App\Models\Category;
use App\Models\Division;
use App\Models\Item;
use App\Models\Loan;
use App\Models\LoanExtension;
use App\Models\LoanItem;
use App\Models\ReturnClaim;
use App\Models\ReturnClaimLine;
use App\Services\StatusPeminjaman as S;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function pinjamanUjiStatus(string $batas = '2026-10-27'): array
{
    $kat = Category::create(['nama' => 'Kabel', 'aktif' => true]);
    $div = Division::create(['nama' => 'IT', 'aktif' => true]);
    $item = Item::create([
        'nama' => 'HDMI', 'kategori_id' => $kat->id,
        'total' => 10, 'tersedia' => 7, 'rusak' => 0, 'boleh_diperpanjang' => true,
    ]);
    $loan = Loan::create([
        'kode_pinjam' => 'PJM-ABCDEF', 'nama_peminjam' => 'Budi', 'division_id' => $div->id,
        'tipe_peminjam' => 'karyawan', 'tanggal_pinjam' => '2026-10-20',
        'batas_kembali_awal' => $batas, 'batas_kembali' => $batas,
    ]);
    $li = LoanItem::create(['loan_id' => $loan->id, 'item_id' => $item->id, 'qty_dipinjam' => 3]);

    return [$loan, $li];
}

it('klaim menunggu memindahkan unit dari O ke P', function () {
    [$loan, $li] = pinjamanUjiStatus('2026-11-05');
    $klaim = ReturnClaim::create(['kode_klaim' => 'KMB-AAAAAA', 'loan_id' => $loan->id, 'status' => 'menunggu']);
    ReturnClaimLine::create(['claim_id' => $klaim->id, 'loan_item_id' => $li->id, 'kondisi_dilaporkan' => 'baik', 'qty' => 2]);

    $hasil = (new S)->dariModel($loan->fresh(), '2026-10-30');

    expect($hasil['status'])->toBe(S::DIPINJAM)
        ->and($hasil['o'])->toBe(1)
        ->and($hasil['p'])->toBe(2);
});

it('klaim yang ditolak seluruhnya mengembalikan unit menjadi sisa (BR-04)', function () {
    [$loan, $li] = pinjamanUjiStatus('2026-10-27');
    $klaim = ReturnClaim::create(['kode_klaim' => 'KMB-BBBBBB', 'loan_id' => $loan->id, 'status' => 'diverifikasi', 'catatan_admin' => 'Barang tidak diserahkan']);
    ReturnClaimLine::create([
        'claim_id' => $klaim->id, 'loan_item_id' => $li->id, 'kondisi_dilaporkan' => 'baik', 'qty' => 3,
        'qty_baik' => 0, 'qty_rusak' => 0, 'qty_hilang' => 0, 'qty_ditolak' => 3,
    ]);

    $hasil = (new S)->dariModel($loan->fresh(), '2026-10-30');

    expect($hasil['status'])->toBe(S::TERLAMBAT)
        ->and($hasil['o'])->toBe(3)
        ->and($hasil['p'])->toBe(0)
        ->and($hasil['hari_terlambat'])->toBe(3)
        ->and($hasil['lencana'])->toContain(S::LENCANA_CATATAN);
});

it('verifikasi baik, rusak, dan hilang menyelesaikan peminjaman', function () {
    [$loan, $li] = pinjamanUjiStatus('2026-11-05');
    $klaim = ReturnClaim::create(['kode_klaim' => 'KMB-CCCCCC', 'loan_id' => $loan->id, 'status' => 'diverifikasi']);
    ReturnClaimLine::create([
        'claim_id' => $klaim->id, 'loan_item_id' => $li->id, 'kondisi_dilaporkan' => 'baik', 'qty' => 3,
        'qty_baik' => 1, 'qty_rusak' => 1, 'qty_hilang' => 1, 'qty_ditolak' => 0,
    ]);

    expect((new S)->dariModel($loan->fresh(), '2026-10-30')['status'])->toBe(S::SELESAI);
});

it('menghitung perpanjangan dan status dibatalkan dari model', function () {
    [$loan] = pinjamanUjiStatus('2026-11-05');
    LoanExtension::create([
        'loan_id' => $loan->id, 'batas_lama' => '2026-11-05', 'batas_baru' => '2026-11-08',
        'tambahan_hari' => 3, 'diajukan_oleh' => 'peminjam',
    ]);

    $hasil = (new S)->dariModel($loan->fresh(), '2026-10-30');
    expect($hasil['diperpanjang_kali'])->toBe(1);

    $loan->update(['dibatalkan_pada' => now()]);
    expect((new S)->dariModel($loan->fresh(), '2026-10-30')['status'])->toBe(S::DIBATALKAN);
});
