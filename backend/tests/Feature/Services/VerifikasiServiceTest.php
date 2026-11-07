<?php

use App\Exceptions\GalatBisnis;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Division;
use App\Models\Item;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\ReturnClaim;
use App\Models\ReturnClaimLine;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StatusPeminjaman;
use App\Services\VerifikasiService;
use App\Support\Tanggal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

afterEach(function () {
    assertInvarian(false);
});

beforeEach(function () {
    $this->admin = User::create([
        'name' => 'Admin', 'email' => 'v28-'.uniqid().'@uji.test',
        'password' => 'rahasia-panjang-123', 'aktif' => true,
    ]);
    $this->layanan = app(VerifikasiService::class);
});

function v28Tgl(int $offset): string
{
    return (new DateTimeImmutable(Tanggal::hariIni(), new DateTimeZone('Asia/Jakarta')))
        ->modify("{$offset} days")->format('Y-m-d');
}

/** Barang dengan stok konsisten BR-01: total, dan sebagian sedang dipinjam. */
function v28Item(string $nama, int $total, int $dipinjam): Item
{
    $kat = Category::firstOrCreate(['nama' => 'Uji'], ['aktif' => true]);

    return Item::create([
        'nama' => $nama, 'kategori_id' => $kat->id,
        'total' => $total, 'tersedia' => $total - $dipinjam, 'rusak' => 0, 'boleh_diperpanjang' => true,
    ]);
}

function v28Loan(): Loan
{
    return Loan::create([
        'kode_pinjam' => 'PJM-'.strtoupper(substr(md5(uniqid()), 0, 6)),
        'nama_peminjam' => 'Budi', 'division_id' => Division::firstOrCreate(['nama' => 'IT'], ['aktif' => true])->id,
        'tipe_peminjam' => 'karyawan', 'tanggal_pinjam' => v28Tgl(-5),
        'batas_kembali_awal' => v28Tgl(5), 'batas_kembali' => v28Tgl(5),
    ]);
}

/**
 * Satu klaim menunggu untuk satu peminjaman. $spec: list of [Item, qtyDipinjam, qtyKlaim, kondisiDilaporkan].
 *
 * @return array{0: ReturnClaim, 1: Loan, 2: array<int, ReturnClaimLine>}
 */
function v28Klaim(array $spec): array
{
    $loan = v28Loan();
    $klaim = ReturnClaim::create([
        'kode_klaim' => 'KMB-'.strtoupper(substr(md5(uniqid()), 0, 6)),
        'loan_id' => $loan->id, 'status' => 'menunggu',
    ]);

    $baris = [];
    foreach ($spec as [$item, $dipinjam, $qtyKlaim, $kondisi]) {
        $li = LoanItem::create(['loan_id' => $loan->id, 'item_id' => $item->id, 'qty_dipinjam' => $dipinjam]);
        $baris[] = ReturnClaimLine::create([
            'claim_id' => $klaim->id, 'loan_item_id' => $li->id,
            'kondisi_dilaporkan' => $kondisi, 'qty' => $qtyKlaim,
        ]);
    }

    return [$klaim, $loan, $baris];
}

function v28Hasil(ReturnClaimLine $baris, int $baik = 0, int $rusak = 0, int $hilang = 0, int $ditolak = 0): array
{
    return ['line_id' => $baris->id, 'qty_baik' => $baik, 'qty_rusak' => $rusak, 'qty_hilang' => $hilang, 'qty_ditolak' => $ditolak];
}

function v28Tangkap(Closure $fn): Exception
{
    try {
        $fn();
    } catch (Exception $e) {
        return $e;
    }

    test()->fail('Seharusnya melempar exception, tetapi berhasil.');
}

function v28Angka(Item $item): array
{
    $i = $item->fresh();

    return [(int) $i->total, (int) $i->tersedia, (int) $i->rusak];
}

function v28Mutasi(?string $jenis = null): int
{
    return StockMovement::when($jenis, fn ($q) => $q->where('jenis', $jenis))->count();
}

it('verifikasi sesuai laporan mengembalikan unit ke tersedia (T-03)', function () {
    $item = v28Item('Kabel', 10, 3);
    [$klaim, , [$b]] = v28Klaim([[$item, 3, 3, 'baik']]);

    $hasil = $this->layanan->proses($klaim->id, [v28Hasil($b, baik: 3)], null, $this->admin->id);

    expect($hasil->status)->toBe('diverifikasi')
        ->and($hasil->diverifikasi_oleh)->toBe($this->admin->id)
        ->and($hasil->diverifikasi_pada)->not->toBeNull()
        ->and(v28Angka($item))->toBe([10, 10, 0])
        ->and(v28Mutasi('VERIFIKASI_BAIK'))->toBe(1)
        ->and(v28Mutasi())->toBe(1);

    $mutasi = StockMovement::first();
    expect([$mutasi->delta_total, $mutasi->delta_tersedia, $mutasi->delta_rusak])->toBe([0, 3, 0])
        ->and([$mutasi->ref_tipe, $mutasi->ref_id, $mutasi->user_id])->toBe(['return_claims', $klaim->id, $this->admin->id]);

    $b->refresh();
    expect([$b->qty_baik, $b->qty_rusak, $b->qty_hilang, $b->qty_ditolak])->toBe([3, 0, 0, 0]);

    $audit = AuditLog::where('aksi', 'verifikasi_klaim')->first();
    expect($audit)->not->toBeNull()->and($audit->entitas_id)->toBe($klaim->id);
});

it('kondisi campuran pada beberapa barang diproses benar (T-09, BR-05, BR-08)', function () {
    $a = v28Item('Barang A', 10, 3);
    $b = v28Item('Barang B', 10, 2);
    [$klaim, , [$la, $lb]] = v28Klaim([[$a, 3, 3, 'baik'], [$b, 2, 2, 'rusak']]);

    $this->layanan->proses($klaim->id, [
        v28Hasil($la, baik: 1, rusak: 1, hilang: 1),
        v28Hasil($lb, rusak: 2),
    ], 'Satu hilang, satu rusak saat diperiksa', $this->admin->id);

    expect(v28Angka($a))->toBe([9, 8, 1])
        ->and(v28Angka($b))->toBe([10, 8, 2])
        ->and(v28Mutasi('VERIFIKASI_BAIK'))->toBe(1)
        ->and(v28Mutasi('VERIFIKASI_RUSAK'))->toBe(2)
        ->and(v28Mutasi('VERIFIKASI_HILANG'))->toBe(1)
        ->and(v28Mutasi())->toBe(4);

    $hilang = StockMovement::where('jenis', 'VERIFIKASI_HILANG')->first();
    expect([$hilang->delta_total, $hilang->delta_tersedia, $hilang->delta_rusak])->toBe([-1, 0, 0]);
});

it('hilang baru mengurangi total setelah verifikasi, bukan saat klaim (BR-08)', function () {
    $item = v28Item('Kabel', 10, 3);
    [$klaim, , [$b]] = v28Klaim([[$item, 3, 3, 'hilang']]);

    expect(v28Angka($item))->toBe([10, 7, 0]);

    $this->layanan->proses($klaim->id, [v28Hasil($b, hilang: 3)], null, $this->admin->id);

    expect(v28Angka($item))->toBe([7, 7, 0]);
});

it('hasil berbeda dari laporan tanpa catatan admin ditolak tanpa mengubah apa pun (T-14, BR-06)', function () {
    $item = v28Item('Kabel', 10, 3);
    [$klaim, , [$b]] = v28Klaim([[$item, 3, 3, 'baik']]);

    $e = v28Tangkap(fn () => $this->layanan->proses($klaim->id, [v28Hasil($b, baik: 2, rusak: 1)], null, $this->admin->id));

    expect($e)->toBeInstanceOf(ValidationException::class)
        ->and(array_key_exists('catatan_admin', $e->errors()))->toBeTrue()
        ->and($klaim->fresh()->status)->toBe('menunggu')
        ->and(v28Angka($item))->toBe([10, 7, 0])
        ->and(v28Mutasi())->toBe(0)
        ->and($b->fresh()->qty_baik)->toBeNull();
});

it('hasil berbeda dengan catatan admin tercatat di buku besar dan audit (T-14)', function () {
    $item = v28Item('Kabel', 10, 3);
    [$klaim, , [$b]] = v28Klaim([[$item, 3, 3, 'baik']]);

    $hasil = $this->layanan->proses($klaim->id, [v28Hasil($b, baik: 2, rusak: 1)], 'Satu unit retak', $this->admin->id);

    expect($hasil->catatan_admin)->toBe('Satu unit retak')
        ->and(v28Angka($item))->toBe([10, 9, 1])
        ->and(StockMovement::where('jenis', 'VERIFIKASI_RUSAK')->first()->catatan)->toBe('Satu unit retak')
        ->and(AuditLog::where('aksi', 'verifikasi_klaim')->first()->detail['berbeda_dari_laporan'])->toBeTrue();
});

it('unit ditolak tidak mengubah stok dan kembali menjadi sisa (BR-04, T-04)', function () {
    $item = v28Item('Kabel', 10, 3);
    [$klaim, $loan, [$b]] = v28Klaim([[$item, 3, 3, 'baik']]);

    $e = v28Tangkap(fn () => $this->layanan->proses($klaim->id, [v28Hasil($b, baik: 1, ditolak: 2)], null, $this->admin->id));
    expect($e)->toBeInstanceOf(ValidationException::class)
        ->and(array_key_exists('catatan_admin', $e->errors()))->toBeTrue();

    $this->layanan->proses($klaim->id, [v28Hasil($b, baik: 1, ditolak: 2)], 'Dua unit tidak diserahkan', $this->admin->id);

    expect(v28Angka($item))->toBe([10, 8, 0])
        ->and(v28Mutasi())->toBe(1);

    $status = (new StatusPeminjaman)->dariModel($loan->fresh(), Tanggal::hariIni());
    expect($status['o'])->toBe(2)->and($status['p'])->toBe(0)->and($status['status'])->toBe(StatusPeminjaman::DIPINJAM);
});

it('seluruh unit ditolak tidak menulis mutasi stok sama sekali', function () {
    $item = v28Item('Kabel', 10, 3);
    [$klaim, $loan, [$b]] = v28Klaim([[$item, 3, 3, 'baik']]);

    $this->layanan->proses($klaim->id, [v28Hasil($b, ditolak: 3)], 'Barang tidak ada di meja', $this->admin->id);

    expect(v28Angka($item))->toBe([10, 7, 0])
        ->and(v28Mutasi())->toBe(0)
        ->and((new StatusPeminjaman)->dariModel($loan->fresh(), Tanggal::hariIni())['o'])->toBe(3);
});

it('menolak jumlah hasil yang tidak sama dengan jumlah klaim', function () {
    $item = v28Item('Kabel', 10, 3);
    [$klaim, , [$b]] = v28Klaim([[$item, 3, 3, 'baik']]);

    $e = v28Tangkap(fn () => $this->layanan->proses($klaim->id, [v28Hasil($b, baik: 2)], 'Kurang', $this->admin->id));

    expect($e)->toBeInstanceOf(ValidationException::class)
        ->and(array_key_exists("lines.{$b->id}", $e->errors()))->toBeTrue()
        ->and($klaim->fresh()->status)->toBe('menunggu')
        ->and(v28Mutasi())->toBe(0);
});

it('menolak isian hasil yang bukan bilangan bulat tidak negatif', function (mixed $nilai) {
    $item = v28Item('Kabel', 10, 3);
    [$klaim, , [$b]] = v28Klaim([[$item, 3, 3, 'baik']]);
    $baris = v28Hasil($b, baik: 3);
    $baris['qty_rusak'] = $nilai;

    $e = v28Tangkap(fn () => $this->layanan->proses($klaim->id, [$baris], 'Uji', $this->admin->id));

    expect($e)->toBeInstanceOf(ValidationException::class)
        ->and($klaim->fresh()->status)->toBe('menunggu')
        ->and(v28Mutasi())->toBe(0);
})->with([[-1], ['1.5'], ['abc'], [null], [[]]]);

it('menolak baris yang tidak lengkap, berlebih, atau ganda', function () {
    $a = v28Item('Barang A', 10, 3);
    $b = v28Item('Barang B', 10, 2);
    [$klaim, , [$la, $lb]] = v28Klaim([[$a, 3, 3, 'baik'], [$b, 2, 2, 'baik']]);

    $kurang = v28Tangkap(fn () => $this->layanan->proses($klaim->id, [v28Hasil($la, baik: 3)], null, $this->admin->id));
    $asing = v28Tangkap(fn () => $this->layanan->proses($klaim->id, [v28Hasil($la, baik: 3), v28Hasil($lb, baik: 2), ['line_id' => 999999, 'qty_baik' => 1, 'qty_rusak' => 0, 'qty_hilang' => 0, 'qty_ditolak' => 0]], null, $this->admin->id));
    $ganda = v28Tangkap(fn () => $this->layanan->proses($klaim->id, [v28Hasil($la, baik: 3), v28Hasil($la, baik: 3)], null, $this->admin->id));
    $kosong = v28Tangkap(fn () => $this->layanan->proses($klaim->id, [], null, $this->admin->id));

    foreach ([$kurang, $asing, $ganda, $kosong] as $e) {
        expect($e)->toBeInstanceOf(ValidationException::class);
    }
    expect($klaim->fresh()->status)->toBe('menunggu')->and(v28Mutasi())->toBe(0);
});

it('verifikasi ulang ditolak 409 dan tidak mengubah stok lagi (BR-07, T-22)', function () {
    $item = v28Item('Kabel', 10, 3);
    [$klaim, , [$b]] = v28Klaim([[$item, 3, 3, 'baik']]);

    $this->layanan->proses($klaim->id, [v28Hasil($b, baik: 3)], null, $this->admin->id);
    $e = v28Tangkap(fn () => $this->layanan->proses($klaim->id, [v28Hasil($b, baik: 3)], null, $this->admin->id));

    expect($e)->toBeInstanceOf(GalatBisnis::class)
        ->and($e->getStatusCode())->toBe(409)
        ->and($e->kode)->toBe('klaim_sudah_diproses')
        ->and(v28Angka($item))->toBe([10, 10, 0])
        ->and(v28Mutasi())->toBe(1);
});

it('klaim yang tidak ada menghasilkan 404', function () {
    $e = v28Tangkap(fn () => $this->layanan->proses(999999, [['line_id' => 1, 'qty_baik' => 1, 'qty_rusak' => 0, 'qty_hilang' => 0, 'qty_ditolak' => 0]], null, $this->admin->id));

    expect($e)->toBeInstanceOf(GalatBisnis::class)->and($e->getStatusCode())->toBe(404);
});

it('catatan admin lebih dari 500 karakter ditolak', function () {
    $item = v28Item('Kabel', 10, 3);
    [$klaim, , [$b]] = v28Klaim([[$item, 3, 3, 'baik']]);

    $e = v28Tangkap(fn () => $this->layanan->proses($klaim->id, [v28Hasil($b, baik: 3)], str_repeat('x', 501), $this->admin->id));

    expect($e)->toBeInstanceOf(ValidationException::class)
        ->and($klaim->fresh()->status)->toBe('menunggu');
});

it('rollback penuh bila stok tidak konsisten sehingga BR-01 atau CHECK dilanggar', function () {
    $item = v28Item('Kabel', 10, 3);
    $item->update(['tersedia' => 9]); // sengaja merusak: tersedia + dipinjam melebihi total
    [$klaim, , [$b]] = v28Klaim([[$item, 3, 3, 'baik']]);

    $e = v28Tangkap(fn () => $this->layanan->proses($klaim->id, [v28Hasil($b, baik: 3)], null, $this->admin->id));

    expect($e)->not->toBeNull()
        ->and($klaim->fresh()->status)->toBe('menunggu')
        ->and(v28Angka($item))->toBe([10, 9, 0])
        ->and(v28Mutasi())->toBe(0)
        ->and($b->fresh()->qty_baik)->toBeNull()
        ->and(AuditLog::where('aksi', 'verifikasi_klaim')->count())->toBe(0);

    $item->update(['tersedia' => 7]); // pulihkan data uji agar invarian valid
});
