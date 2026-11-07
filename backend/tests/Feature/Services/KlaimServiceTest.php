<?php

use App\Exceptions\GalatBisnis;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Division;
use App\Models\Item;
use App\Models\Loan;
use App\Models\ReturnClaim;
use App\Models\ReturnClaimLine;
use App\Models\StockMovement;
use App\Services\KlaimService;
use App\Services\PeminjamanService;
use App\Services\StatusPeminjaman;
use App\Support\Tanggal;
use Database\Seeders\SettingSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

afterEach(function () {
    assertInvarian(false);
});

beforeEach(function () {
    $this->seed(SettingSeeder::class);
    $this->klaim = app(KlaimService::class);
});

function k27Tgl(int $offset = 0): string
{
    return (new DateTimeImmutable(Tanggal::hariIni(), new DateTimeZone('Asia/Jakarta')))
        ->modify("{$offset} days")->format('Y-m-d');
}

/** @return array{0: Loan, 1: Item} */
function k27Pinjam(int $qty = 3, int $stok = 10): array
{
    $kategori = Category::firstOrCreate(['nama' => 'Uji'], ['aktif' => true]);
    $item = Item::create([
        'nama' => 'Kabel HDMI', 'kategori_id' => $kategori->id,
        'total' => $stok, 'tersedia' => $stok, 'rusak' => 0, 'boleh_diperpanjang' => true,
    ]);

    $loan = app(PeminjamanService::class)->buat([
        'nama_peminjam' => 'Budi Santoso',
        'division_id' => Division::firstOrCreate(['nama' => 'IT'], ['aktif' => true])->id,
        'tipe_peminjam' => 'karyawan',
        'telepon' => '081234567890',
        'tanggal_pinjam' => k27Tgl(0),
        'batas_kembali' => k27Tgl(3),
        'idempotency_key' => (string) Str::uuid(),
        'items' => [['item_id' => $item->id, 'qty' => $qty]],
    ]);

    return [Loan::find($loan->id), $item->refresh()];
}

function k27Data(Loan $loan, Item $item, int $baik = 0, int $rusak = 0, int $hilang = 0, array $override = []): array
{
    return array_merge([
        'kode_pinjam' => $loan->kode_pinjam,
        'idempotency_key' => (string) Str::uuid(),
        'catatan_peminjam' => null,
        'baris' => [['item_id' => $item->id, 'baik' => $baik, 'rusak' => $rusak, 'hilang' => $hilang]],
    ], $override);
}

function k27Status(Loan $loan): array
{
    return app(StatusPeminjaman::class)->dariModel(Loan::find($loan->id), Tanggal::hariIni());
}

function k27Tangkap(Closure $fn): Exception
{
    try {
        $fn();
    } catch (Exception $e) {
        return $e;
    }

    test()->fail('Seharusnya melempar exception, tetapi berhasil.');
}

it('klaim sah membuat klaim menunggu tanpa mengubah stok (BR-03)', function () {
    [$loan, $item] = k27Pinjam(3, 10);

    $klaim = $this->klaim->buat(k27Data($loan, $item, baik: 1, rusak: 1, override: ['catatan_peminjam' => 'Satu retak']));

    expect(ReturnClaim::count())->toBe(1);
    expect($klaim->status)->toBe('menunggu');
    expect($klaim->catatan_peminjam)->toBe('Satu retak');
    expect($klaim->kode_klaim)->toMatch('/^KMB-[A-HJKMNP-Z2-9]{6}$/');
    expect($klaim->lines->pluck('qty', 'kondisi_dilaporkan')->all())->toEqual(['baik' => 1, 'rusak' => 1]);
    expect($klaim->lines->every(fn ($l) => $l->qty_baik === null && $l->qty_rusak === null && $l->qty_hilang === null && $l->qty_ditolak === null))->toBeTrue();

    $item->refresh();
    expect([$item->total, $item->tersedia, $item->rusak])->toBe([10, 7, 0]);
    expect(StockMovement::count())->toBe(1);
    expect(AuditLog::where('aksi', 'buat_klaim')->count())->toBe(1);
});

it('klaim memindahkan unit dari dipinjam ke menunggu verifikasi (T-07)', function () {
    [$loan, $item] = k27Pinjam(3);

    $this->klaim->buat(k27Data($loan, $item, baik: 2));
    $status = k27Status($loan);

    expect($status['status'])->toBe(StatusPeminjaman::DIPINJAM);
    expect([$status['o'], $status['p']])->toBe([1, 2]);
    expect($status['lencana'])->toContain(StatusPeminjaman::LENCANA_SEBAGIAN, StatusPeminjaman::LENCANA_KLAIM_MENUNGGU);
});

it('klaim melebihi sisa ditolak 409, termasuk unit yang menunggu verifikasi (T-08)', function () {
    [$loan, $item] = k27Pinjam(3);

    $this->klaim->buat(k27Data($loan, $item, baik: 2));

    $e = k27Tangkap(fn () => $this->klaim->buat(k27Data($loan, $item, baik: 2)));
    expect($e)->toBeInstanceOf(GalatBisnis::class)->and($e->getStatusCode())->toBe(409);
    expect(ReturnClaim::count())->toBe(1);

    $this->klaim->buat(k27Data($loan, $item, hilang: 1));
    expect(ReturnClaim::count())->toBe(2);

    $e = k27Tangkap(fn () => $this->klaim->buat(k27Data($loan, $item, baik: 1)));
    expect($e)->toBeInstanceOf(GalatBisnis::class)->and($e->getStatusCode())->toBe(409);
    expect(ReturnClaim::count())->toBe(2);
    expect(k27Status($loan)['status'])->toBe(StatusPeminjaman::MENUNGGU_VERIFIKASI);
});

it('klaim atas pemesanan ditolak 409 (T-25)', function () {
    [$loan, $item] = k27Pinjam(3);
    Loan::whereKey($loan->id)->update(['tanggal_pinjam' => k27Tgl(3), 'batas_kembali' => k27Tgl(5)]);

    $e = k27Tangkap(fn () => $this->klaim->buat(k27Data($loan, $item, baik: 1)));

    expect($e)->toBeInstanceOf(GalatBisnis::class)->and($e->getStatusCode())->toBe(409);
    expect(ReturnClaim::count())->toBe(0);
    expect(ReturnClaimLine::count())->toBe(0);
});

it('klaim atas peminjaman yang dibatalkan ditolak 409', function () {
    [$loan, $item] = k27Pinjam(3);
    $item->increment('tersedia', 3);
    Loan::whereKey($loan->id)->update(['dibatalkan_pada' => now()]);

    $e = k27Tangkap(fn () => $this->klaim->buat(k27Data($loan, $item, baik: 1)));

    expect($e)->toBeInstanceOf(GalatBisnis::class)->and($e->getStatusCode())->toBe(409);
    expect(ReturnClaim::count())->toBe(0);
});

it('klaim membekukan hari terlambat dan menghentikan hitungan saat semua unit diklaim (T-12)', function () {
    [$loan, $item] = k27Pinjam(3);
    Loan::whereKey($loan->id)->update(['tanggal_pinjam' => k27Tgl(-10), 'batas_kembali' => k27Tgl(-2)]);

    expect(k27Status($loan)['hari_terlambat'])->toBe(2);

    $sebagian = $this->klaim->buat(k27Data($loan, $item, baik: 1));
    expect($sebagian->hari_terlambat)->toBe(2);
    expect(k27Status($loan)['status'])->toBe(StatusPeminjaman::TERLAMBAT);

    $sisanya = $this->klaim->buat(k27Data($loan, $item, baik: 2));
    expect($sisanya->hari_terlambat)->toBe(2);

    $status = k27Status($loan);
    expect($status['status'])->toBe(StatusPeminjaman::MENUNGGU_VERIFIKASI);
    expect($status['hari_terlambat'])->toBe(0);
});

it('klaim tepat waktu membekukan nol hari terlambat', function () {
    [$loan, $item] = k27Pinjam(3);

    $klaim = $this->klaim->buat(k27Data($loan, $item, baik: 3));

    expect($klaim->hari_terlambat)->toBe(0);
});

it('menolak isian tidak sah tanpa efek apa pun', function () {
    [$loan, $item] = k27Pinjam(3);

    $kasus = [
        'baris kosong' => ['baris' => []],
        'semua nol' => null,
        'negatif' => ['baris' => [['item_id' => $item->id, 'baik' => -1, 'rusak' => 2, 'hilang' => 0]]],
        'barang bukan milik peminjaman' => ['baris' => [['item_id' => 999999, 'baik' => 1, 'rusak' => 0, 'hilang' => 0]]],
        'barang ganda' => ['baris' => [
            ['item_id' => $item->id, 'baik' => 1, 'rusak' => 0, 'hilang' => 0],
            ['item_id' => $item->id, 'baik' => 1, 'rusak' => 0, 'hilang' => 0],
        ]],
        'catatan 501 karakter' => ['catatan_peminjam' => str_repeat('x', 501)],
        'tanpa kunci idempotensi' => ['idempotency_key' => ''],
    ];

    foreach ($kasus as $nama => $override) {
        $data = $override === null
            ? k27Data($loan, $item, 0, 0, 0)
            : k27Data($loan, $item, baik: 1, override: $override);

        expect(fn () => $this->klaim->buat($data))
            ->toThrow(ValidationException::class, '', "kasus: {$nama}");
    }

    expect(ReturnClaim::count())->toBe(0);
    expect(ReturnClaimLine::count())->toBe(0);
    expect(AuditLog::where('aksi', 'buat_klaim')->count())->toBe(0);
});

it('menolak kode pinjam yang tidak ada', function () {
    [$loan, $item] = k27Pinjam(3);

    expect(fn () => $this->klaim->buat(k27Data($loan, $item, baik: 1, override: ['kode_pinjam' => 'PJM-ZZZZZZ'])))
        ->toThrow(ModelNotFoundException::class);

    expect(ReturnClaim::count())->toBe(0);
});

it('kunci sama dan isi sama menghasilkan satu klaim, isi beda ditolak 409 (T-11)', function () {
    [$loan, $item] = k27Pinjam(3);
    $data = k27Data($loan, $item, baik: 1);

    $pertama = $this->klaim->buat($data);
    $kedua = $this->klaim->buat($data);

    expect($kedua->id)->toBe($pertama->id);
    expect(ReturnClaim::count())->toBe(1);
    expect(ReturnClaimLine::count())->toBe(1);

    $beda = $data;
    $beda['baris'][0]['baik'] = 2;

    $e = k27Tangkap(fn () => $this->klaim->buat($beda));
    expect($e)->toBeInstanceOf(GalatBisnis::class)->and($e->getStatusCode())->toBe(409);
    expect(ReturnClaim::count())->toBe(1);
});

it('dua klaim mendapat kode klaim berbeda', function () {
    [$loan, $item] = k27Pinjam(3);

    $a = $this->klaim->buat(k27Data($loan, $item, baik: 1));
    $b = $this->klaim->buat(k27Data($loan, $item, baik: 1));

    expect($a->kode_klaim)->not->toBe($b->kode_klaim);
});
