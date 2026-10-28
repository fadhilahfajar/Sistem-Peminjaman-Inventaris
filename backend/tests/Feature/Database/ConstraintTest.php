<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

const E_CHECK = 3819;
const E_UNIK = 1062;
const E_FK_TAMBAH = 1452;
const E_FK_HAPUS = 1451;
const E_ENUM = 1265;
const E_NOT_NULL = 1048;
const E_JSON = 3140;

/** Operasi harus ditolak database dengan kode error tertentu. */
function ditolak(Closure $fn, int $kode): void
{
    try {
        $fn();
    } catch (QueryException $e) {
        expect($e->errorInfo[1])->toBe($kode, $e->getMessage());

        return;
    }
    test()->fail("Seharusnya ditolak dengan kode {$kode}, tetapi berhasil.");
}

function bKategori(): int
{
    return DB::table('categories')->insertGetId(['nama' => 'Kat '.uniqid()]);
}

function bDivisi(): int
{
    return DB::table('divisions')->insertGetId(['nama' => 'Div '.uniqid()]);
}

function bBarang(array $o = []): int
{
    return DB::table('items')->insertGetId(array_merge([
        'nama' => 'Barang '.uniqid(), 'kategori_id' => bKategori(),
        'total' => 10, 'tersedia' => 10, 'rusak' => 0,
    ], $o));
}

function bPinjam(array $o = []): int
{
    return DB::table('loans')->insertGetId(array_merge([
        'kode_pinjam' => 'PJM-'.strtoupper(substr(md5(uniqid()), 0, 6)),
        'nama_peminjam' => 'Budi', 'division_id' => bDivisi(), 'tipe_peminjam' => 'karyawan',
        'tanggal_pinjam' => '2026-10-28', 'batas_kembali_awal' => '2026-11-04',
        'batas_kembali' => '2026-11-04',
    ], $o));
}

function bLoanItem(?int $loan = null, ?int $item = null, int $qty = 3): int
{
    return DB::table('loan_items')->insertGetId([
        'loan_id' => $loan ?? bPinjam(), 'item_id' => $item ?? bBarang(), 'qty_dipinjam' => $qty,
    ]);
}

function bKlaim(?int $loan = null, array $o = []): int
{
    return DB::table('return_claims')->insertGetId(array_merge([
        'kode_klaim' => 'KMB-'.strtoupper(substr(md5(uniqid()), 0, 6)),
        'loan_id' => $loan ?? bPinjam(),
    ], $o));
}

function bBaris(int $klaim, int $loanItem, array $o = []): array
{
    return array_merge([
        'claim_id' => $klaim, 'loan_item_id' => $loanItem,
        'kondisi_dilaporkan' => 'baik', 'qty' => 3,
    ], $o);
}

function bUser(): int
{
    return DB::table('users')->insertGetId([
        'name' => 'Admin', 'email' => uniqid().'@uji.test', 'password' => 'x',
    ]);
}

// ---- tabel ----

it('memiliki semua tabel yang diharapkan', function () {
    foreach ([
        'users', 'divisions', 'categories', 'settings', 'items', 'loans', 'loan_items',
        'return_claims', 'return_claim_lines', 'loan_extensions', 'loan_corrections',
        'stock_movements', 'audit_logs', 'sessions', 'cache', 'cache_locks', 'migrations',
    ] as $t) {
        expect(Schema::hasTable($t))->toBeTrue("Tabel {$t} tidak ada");
    }
});

it('tidak memiliki tabel yang sengaja dihapus', function () {
    foreach (['jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens', 'personal_access_tokens'] as $t) {
        expect(Schema::hasTable($t))->toBeFalse("Tabel {$t} seharusnya tidak ada");
    }
});

// ---- master data ----

it('menolak aktif selain 0 atau 1', function () {
    ditolak(fn () => DB::table('divisions')->insert(['nama' => 'X', 'aktif' => 2]), E_CHECK);
    ditolak(fn () => DB::table('categories')->insert(['nama' => 'X', 'aktif' => 2]), E_CHECK);
    ditolak(fn () => DB::table('users')->insert([
        'name' => 'A', 'email' => 'a@uji.test', 'password' => 'x', 'aktif' => 2,
    ]), E_CHECK);
});

it('menolak nama divisi, nama kategori, dan email ganda', function () {
    DB::table('divisions')->insert(['nama' => 'IT']);
    ditolak(fn () => DB::table('divisions')->insert(['nama' => 'IT']), E_UNIK);
    DB::table('categories')->insert(['nama' => 'Kabel']);
    ditolak(fn () => DB::table('categories')->insert(['nama' => 'Kabel']), E_UNIK);
    DB::table('users')->insert(['name' => 'A', 'email' => 'a@uji.test', 'password' => 'x']);
    ditolak(fn () => DB::table('users')->insert(['name' => 'B', 'email' => 'a@uji.test', 'password' => 'x']), E_UNIK);
});

// ---- items ----

it('menerima barang valid', function () {
    expect(bBarang(['total' => 10, 'tersedia' => 7, 'rusak' => 3]))->toBeInt();
});

it('menolak tersedia + rusak > total', function () {
    ditolak(fn () => bBarang(['total' => 5, 'tersedia' => 4, 'rusak' => 2]), E_CHECK);
});

it('menolak stok negatif', function () {
    ditolak(fn () => bBarang(['total' => -1, 'tersedia' => 0]), E_CHECK);
    ditolak(fn () => bBarang(['tersedia' => -1]), E_CHECK);
    ditolak(fn () => bBarang(['rusak' => -1]), E_CHECK);
});

it('menolak boleh_diperpanjang selain 0 atau 1', function () {
    ditolak(fn () => bBarang(['boleh_diperpanjang' => 2]), E_CHECK);
});

it('menolak nama barang aktif ganda, tetapi mengizinkan nama barang yang sudah dihapus', function () {
    $k = bKategori();
    bBarang(['nama' => 'Proyektor', 'kategori_id' => $k]);
    ditolak(fn () => bBarang(['nama' => 'Proyektor', 'kategori_id' => $k]), E_UNIK);

    DB::table('items')->where('nama', 'Proyektor')->update(['dihapus_pada' => now()]);
    expect(bBarang(['nama' => 'Proyektor', 'kategori_id' => $k]))->toBeInt();
});

it('menolak kategori yang tidak ada dan penghapusan kategori yang dipakai', function () {
    ditolak(fn () => bBarang(['kategori_id' => 999999]), E_FK_TAMBAH);
    $k = bKategori();
    bBarang(['kategori_id' => $k]);
    ditolak(fn () => DB::table('categories')->where('id', $k)->delete(), E_FK_HAPUS);
});

// ---- loans dan loan_items ----

it('menerima peminjaman valid', function () {
    expect(bPinjam())->toBeInt();
});

it('menolak batas_kembali sebelum tanggal_pinjam', function () {
    ditolak(fn () => bPinjam(['tanggal_pinjam' => '2026-10-28', 'batas_kembali' => '2026-10-27']), E_CHECK);
});

it('menolak kode_pinjam dan idempotency_key ganda, tetapi mengizinkan banyak NULL', function () {
    bPinjam(['kode_pinjam' => 'PJM-AAAAAA', 'idempotency_key' => '11111111-1111-1111-1111-111111111111']);
    ditolak(fn () => bPinjam(['kode_pinjam' => 'PJM-AAAAAA']), E_UNIK);
    ditolak(fn () => bPinjam(['idempotency_key' => '11111111-1111-1111-1111-111111111111']), E_UNIK);

    bPinjam(['idempotency_key' => null]);
    expect(bPinjam(['idempotency_key' => null]))->toBeInt();
});

it('menolak tipe_peminjam di luar enum dan divisi yang tidak ada', function () {
    ditolak(fn () => bPinjam(['tipe_peminjam' => 'tamu']), E_ENUM);
    ditolak(fn () => bPinjam(['division_id' => 999999]), E_FK_TAMBAH);
});

it('menolak qty_dipinjam kurang dari 1 dan pasangan loan-item ganda', function () {
    ditolak(fn () => bLoanItem(qty: 0), E_CHECK);

    $loan = bPinjam();
    $item = bBarang();
    bLoanItem($loan, $item);
    ditolak(fn () => bLoanItem($loan, $item), E_UNIK);
});

it('menolak hapus barang yang sudah dipinjam dan peminjaman yang punya barang', function () {
    $loan = bPinjam();
    $item = bBarang();
    bLoanItem($loan, $item);
    ditolak(fn () => DB::table('items')->where('id', $item)->delete(), E_FK_HAPUS);
    ditolak(fn () => DB::table('loans')->where('id', $loan)->delete(), E_FK_HAPUS);
});

// ---- return_claims ----

it('menolak hari_terlambat negatif dan status di luar enum', function () {
    ditolak(fn () => bKlaim(null, ['hari_terlambat' => -1]), E_CHECK);
    ditolak(fn () => bKlaim(null, ['status' => 'selesai']), E_ENUM);
});

it('menolak kode_klaim dan idempotency_key klaim ganda', function () {
    $loan = bPinjam();
    bKlaim($loan, ['kode_klaim' => 'KMB-AAAAAA', 'idempotency_key' => '22222222-2222-2222-2222-222222222222']);
    ditolak(fn () => bKlaim($loan, ['kode_klaim' => 'KMB-AAAAAA']), E_UNIK);
    ditolak(fn () => bKlaim($loan, ['idempotency_key' => '22222222-2222-2222-2222-222222222222']), E_UNIK);
});

// ---- return_claim_lines (BR-05) ----

it('menerima baris klaim belum diverifikasi dan baris terverifikasi yang jumlahnya cocok', function () {
    DB::table('return_claim_lines')->insert(bBaris(bKlaim(), bLoanItem()));
    DB::table('return_claim_lines')->insert(bBaris(bKlaim(), bLoanItem(), [
        'qty_baik' => 1, 'qty_rusak' => 1, 'qty_hilang' => 0, 'qty_ditolak' => 1,
    ]));
    expect(DB::table('return_claim_lines')->count())->toBe(2);
});

it('menolak sebagian qty_* NULL', function () {
    ditolak(fn () => DB::table('return_claim_lines')->insert(
        bBaris(bKlaim(), bLoanItem(), ['qty_baik' => 3])
    ), E_CHECK);
});

it('menolak jumlah hasil tidak sama dengan qty', function () {
    ditolak(fn () => DB::table('return_claim_lines')->insert(bBaris(bKlaim(), bLoanItem(), [
        'qty_baik' => 1, 'qty_rusak' => 1, 'qty_hilang' => 0, 'qty_ditolak' => 0,
    ])), E_CHECK);
});

it('menolak hasil negatif walau jumlahnya cocok', function () {
    ditolak(fn () => DB::table('return_claim_lines')->insert(bBaris(bKlaim(), bLoanItem(), [
        'qty_baik' => 4, 'qty_rusak' => -1, 'qty_hilang' => 0, 'qty_ditolak' => 0,
    ])), E_CHECK);
});

it('menolak qty klaim kurang dari 1, kondisi di luar enum, dan kombinasi ganda', function () {
    $li = bLoanItem();
    $klaim = bKlaim();
    ditolak(fn () => DB::table('return_claim_lines')->insert(bBaris($klaim, $li, ['qty' => 0])), E_CHECK);
    ditolak(fn () => DB::table('return_claim_lines')->insert(bBaris($klaim, $li, ['kondisi_dilaporkan' => 'bagus'])), E_ENUM);

    DB::table('return_claim_lines')->insert(bBaris($klaim, $li));
    ditolak(fn () => DB::table('return_claim_lines')->insert(bBaris($klaim, $li)), E_UNIK);
});

// ---- tabel riwayat ----

it('menolak perpanjangan dengan tambahan_hari < 1 atau pengaju di luar enum', function () {
    $base = ['loan_id' => bPinjam(), 'batas_lama' => '2026-11-04', 'batas_baru' => '2026-11-06'];
    ditolak(fn () => DB::table('loan_extensions')->insert($base + ['tambahan_hari' => 0, 'diajukan_oleh' => 'peminjam']), E_CHECK);
    ditolak(fn () => DB::table('loan_extensions')->insert($base + ['tambahan_hari' => 2, 'diajukan_oleh' => 'tamu']), E_ENUM);
});

it('menolak koreksi tanpa alasan atau tanpa user_id', function () {
    $loan = bPinjam();
    ditolak(fn () => DB::table('loan_corrections')->insert([
        'loan_id' => $loan, 'kolom' => 'nama_peminjam', 'user_id' => bUser(), 'alasan' => null,
    ]), E_NOT_NULL);
    ditolak(fn () => DB::table('loan_corrections')->insert([
        'loan_id' => $loan, 'kolom' => 'nama_peminjam', 'alasan' => 'salah ketik', 'user_id' => null,
    ]), E_NOT_NULL);
});

it('menolak jenis mutasi stok di luar enum dan barang yang tidak ada, menerima yang valid', function () {
    $item = bBarang();
    ditolak(fn () => DB::table('stock_movements')->insert(['item_id' => $item, 'jenis' => 'HADIAH']), E_ENUM);
    ditolak(fn () => DB::table('stock_movements')->insert(['item_id' => 999999, 'jenis' => 'TAMBAH_STOK']), E_FK_TAMBAH);

    DB::table('stock_movements')->insert([
        'item_id' => $item, 'jenis' => 'TAMBAH_STOK', 'delta_total' => 1, 'delta_tersedia' => 1,
    ]);
    ditolak(fn () => DB::table('items')->where('id', $item)->delete(), E_FK_HAPUS);
});

it('menolak detail audit yang bukan JSON dan menerima yang valid', function () {
    ditolak(fn () => DB::table('audit_logs')->insert([
        'aksi' => 'uji', 'entitas' => 'items', 'detail' => '{bukan json',
    ]), E_JSON);

    DB::table('audit_logs')->insert([
        'aksi' => 'uji', 'entitas' => 'items', 'detail' => json_encode(['a' => 1]),
    ]);
    expect(DB::table('audit_logs')->count())->toBe(1);
});
