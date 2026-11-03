<?php

use App\Exceptions\GalatBisnis;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Division;
use App\Models\Item;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\StockMovement;
use App\Services\PeminjamanService;
use App\Services\StatusPeminjaman;
use App\Support\Tanggal;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SettingSeeder::class);
    $this->layanan = app(PeminjamanService::class);
});

// Helper lokal berkas ini. Barang dibuat langsung lewat model (pengecualian aturan 4 untuk data awal tes).
function p25Barang(string $nama = 'Kabel HDMI', int $stok = 10): Item
{
    $kategori = Category::firstOrCreate(['nama' => 'Uji'], ['aktif' => true]);

    return Item::create([
        'nama' => $nama, 'kategori_id' => $kategori->id,
        'total' => $stok, 'tersedia' => $stok, 'rusak' => 0, 'boleh_diperpanjang' => true,
    ]);
}

function p25Tgl(int $offset = 0): string
{
    return (new DateTimeImmutable(Tanggal::hariIni(), new DateTimeZone('Asia/Jakarta')))
        ->modify("{$offset} days")->format('Y-m-d');
}

function p25Data(Item $item, int $qty = 3, array $override = []): array
{
    return array_merge([
        'nama_peminjam' => 'Budi Santoso',
        'division_id' => Division::firstOrCreate(['nama' => 'IT'], ['aktif' => true])->id,
        'tipe_peminjam' => 'karyawan',
        'telepon' => '081234567890',
        'tanggal_pinjam' => p25Tgl(0),
        'batas_kembali' => p25Tgl(3),
        'idempotency_key' => (string) Str::uuid(),
        'items' => [['item_id' => $item->id, 'qty' => $qty]],
    ], $override);
}

/** Menangkap exception bisnis. Error PHP sengaja tidak ditangkap agar pesan aslinya terlihat. */
function p25Tangkap(Closure $fn): Exception
{
    try {
        $fn();
    } catch (Exception $e) {
        return $e;
    }

    test()->fail('Seharusnya melempar exception, tetapi berhasil.');
}

function p25TidakAdaEfek(): void
{
    expect(Loan::count())->toBe(0)
        ->and(LoanItem::count())->toBe(0)
        ->and(StockMovement::where('jenis', 'PINJAM')->count())->toBe(0)
        ->and(AuditLog::where('aksi', 'buat_peminjaman')->count())->toBe(0);
}

it('membuat peminjaman hari ini: stok berkurang, kode, mutasi PINJAM, dan audit (T-02)', function () {
    $item = p25Barang('Kabel HDMI', 10);

    $loan = $this->layanan->buat(p25Data($item, 3));

    expect($loan->kode_pinjam)->toMatch('/^PJM-[A-HJKMNP-Z2-9]{6}$/')
        ->and($loan->tanggal_pinjam->format('Y-m-d'))->toBe(Tanggal::hariIni())
        ->and($loan->batas_kembali->format('Y-m-d'))->toBe(p25Tgl(3))
        ->and($loan->batas_kembali_awal->format('Y-m-d'))->toBe(p25Tgl(3))
        ->and($loan->items)->toHaveCount(1)
        ->and($loan->items->first()->qty_dipinjam)->toBe(3);

    $item->refresh();
    expect([$item->total, $item->tersedia, $item->rusak])->toBe([10, 7, 0]);

    $mutasi = StockMovement::where('item_id', $item->id)->where('jenis', 'PINJAM')->get();
    expect($mutasi)->toHaveCount(1)
        ->and($mutasi->first()->delta_tersedia)->toBe(-3)
        ->and($mutasi->first()->user_id)->toBeNull();

    $audit = AuditLog::where('aksi', 'buat_peminjaman')->first();
    expect($audit)->not->toBeNull()
        ->and(json_encode($audit->detail))->not->toContain('0812');
});

it('menolak jumlah melebihi tersedia dengan 409 tanpa efek (T-02)', function () {
    $item = p25Barang('Kabel HDMI', 2);

    $e = p25Tangkap(fn () => $this->layanan->buat(p25Data($item, 3)));

    expect($e)->toBeInstanceOf(GalatBisnis::class)
        ->and($e->getStatusCode())->toBe(409)
        ->and($e->getMessage())->toContain('Kabel HDMI');
    p25TidakAdaEfek();
    expect($item->fresh()->tersedia)->toBe(2);
});

it('membatalkan semuanya bila barang kedua gagal, tanpa data parsial (T-34)', function () {
    $a = p25Barang('Barang A', 5);
    $b = p25Barang('Barang B', 1);

    $e = p25Tangkap(fn () => $this->layanan->buat(p25Data($a, 2, [
        'items' => [['item_id' => $a->id, 'qty' => 2], ['item_id' => $b->id, 'qty' => 2]],
    ])));

    expect($e)->toBeInstanceOf(GalatBisnis::class)->and($e->getMessage())->toContain('Barang B');
    p25TidakAdaEfek();
    expect($a->fresh()->tersedia)->toBe(5)->and($b->fresh()->tersedia)->toBe(1);
});

it('klik ganda dengan kunci sama menghasilkan satu peminjaman walau stok sudah habis (T-11)', function () {
    $item = p25Barang('Kabel', 3);
    $data = p25Data($item, 3);

    $pertama = $this->layanan->buat($data);
    $kedua = $this->layanan->buat($data);

    expect($kedua->id)->toBe($pertama->id)
        ->and($kedua->kode_pinjam)->toBe($pertama->kode_pinjam)
        ->and(Loan::count())->toBe(1)
        ->and(StockMovement::where('jenis', 'PINJAM')->count())->toBe(1)
        ->and($item->fresh()->tersedia)->toBe(0);
});

it('kunci sama dengan isi berbeda ditolak 409 dan tidak mengubah stok (BR-20)', function () {
    $item = p25Barang('Kabel', 10);
    $data = p25Data($item, 3);
    $this->layanan->buat($data);

    $e = p25Tangkap(fn () => $this->layanan->buat([...$data, 'items' => [['item_id' => $item->id, 'qty' => 4]]]));

    expect($e)->toBeInstanceOf(GalatBisnis::class)
        ->and($e->getStatusCode())->toBe(409)
        ->and($e->kode)->toBe('kunci_idempotensi_bentrok')
        ->and($item->fresh()->tersedia)->toBe(7);
});

it('menolak barang yang sama dua kali dan peminjaman tanpa barang (BR-22)', function () {
    $item = p25Barang('Kabel', 10);

    $dobel = p25Tangkap(fn () => $this->layanan->buat(p25Data($item, 1, [
        'items' => [['item_id' => $item->id, 'qty' => 1], ['item_id' => $item->id, 'qty' => 2]],
    ])));
    $kosong = p25Tangkap(fn () => $this->layanan->buat(p25Data($item, 1, ['items' => []])));

    expect($dobel)->toBeInstanceOf(ValidationException::class)
        ->and(array_key_exists('items.1.item_id', $dobel->errors()))->toBeTrue()
        ->and($kosong)->toBeInstanceOf(ValidationException::class)
        ->and(array_key_exists('items', $kosong->errors()))->toBeTrue();
    p25TidakAdaEfek();
});

it('menolak isian tidak sah tanpa mengubah apa pun (BR-22, BR-23, T-32)', function (array $override, string $kolom) {
    $item = p25Barang('Kabel', 10);

    $e = p25Tangkap(fn () => $this->layanan->buat(p25Data($item, 1, $override)));

    expect($e)->toBeInstanceOf(ValidationException::class)
        ->and(array_key_exists($kolom, $e->errors()))->toBeTrue();
    p25TidakAdaEfek();
    expect($item->fresh()->tersedia)->toBe(10);
})->with(fn () => [
    'tanggal lampau' => [['tanggal_pinjam' => p25Tgl(-1), 'batas_kembali' => p25Tgl(2)], 'tanggal_pinjam'],
    'melebihi maks_booking_hari' => [['tanggal_pinjam' => p25Tgl(8), 'batas_kembali' => p25Tgl(9)], 'tanggal_pinjam'],
    'format tanggal salah' => [['tanggal_pinjam' => '30-10-2026'], 'tanggal_pinjam'],
    'tanggal tidak ada di kalender' => [['tanggal_pinjam' => '2026-02-30'], 'tanggal_pinjam'],
    'batas sebelum tanggal pinjam' => [['batas_kembali' => p25Tgl(-1)], 'batas_kembali'],
    'batas melebihi maks_durasi_awal_hari' => [['batas_kembali' => p25Tgl(8)], 'batas_kembali'],
    'tipe kosong' => [['tipe_peminjam' => ''], 'tipe_peminjam'],
    'tipe tidak dikenal' => [['tipe_peminjam' => 'tamu'], 'tipe_peminjam'],
    'nama satu huruf' => [['nama_peminjam' => 'A'], 'nama_peminjam'],
    'nama 101 karakter' => [['nama_peminjam' => str_repeat('x', 101)], 'nama_peminjam'],
    'telepon terlalu pendek' => [['telepon' => '123'], 'telepon'],
    'telepon berisi huruf' => [['telepon' => '08abc12345'], 'telepon'],
    'kunci bukan uuid' => [['idempotency_key' => 'bukan-uuid'], 'idempotency_key'],
    'divisi kosong' => [['division_id' => null], 'division_id'],
]);

it('menolak jumlah yang bukan bilangan bulat minimal satu', function (mixed $qty) {
    $item = p25Barang('Kabel', 10);

    $e = p25Tangkap(fn () => $this->layanan->buat(p25Data($item, 1, [
        'items' => [['item_id' => $item->id, 'qty' => $qty]],
    ])));

    expect($e)->toBeInstanceOf(ValidationException::class)
        ->and(array_key_exists('items.0.qty', $e->errors()))->toBeTrue();
    p25TidakAdaEfek();
})->with([[0], [-1], ['2.5'], ['dua'], [null]]);

it('menolak divisi nonaktif (BR-27) dan barang yang sudah dihapus', function () {
    $item = p25Barang('Kabel', 10);
    $lama = Division::create(['nama' => 'Lama', 'aktif' => false]);

    $a = p25Tangkap(fn () => $this->layanan->buat(p25Data($item, 1, ['division_id' => $lama->id])));
    expect($a)->toBeInstanceOf(ValidationException::class)
        ->and(array_key_exists('division_id', $a->errors()))->toBeTrue();

    $item->delete();
    $b = p25Tangkap(fn () => $this->layanan->buat(p25Data($item, 1)));
    expect($b)->toBeInstanceOf(ValidationException::class)
        ->and(array_key_exists('items.0.item_id', $b->errors()))->toBeTrue();
    p25TidakAdaEfek();
});

it('menerima tipe magang dan telepon kosong, dan menyimpan telepon kosong sebagai null (T-32)', function () {
    $item = p25Barang('Kabel', 10);

    $loan = $this->layanan->buat(p25Data($item, 1, ['tipe_peminjam' => 'magang', 'telepon' => '']));

    expect($loan->tipe_peminjam)->toBe('magang')->and($loan->telepon)->toBeNull();
});

it('meminjam beberapa barang sekaligus menulis satu mutasi per barang', function () {
    $a = p25Barang('Barang A', 5);
    $b = p25Barang('Barang B', 5);

    $loan = $this->layanan->buat(p25Data($a, 2, [
        'items' => [['item_id' => $b->id, 'qty' => 1], ['item_id' => $a->id, 'qty' => 2]],
    ]));

    expect($loan->items)->toHaveCount(2)
        ->and($a->fresh()->tersedia)->toBe(3)
        ->and($b->fresh()->tersedia)->toBe(4)
        ->and(StockMovement::where('jenis', 'PINJAM')->count())->toBe(2);
});

it('pemesanan tanggal masa depan menahan stok sejak dibuat dan berstatus dipesan (T-23)', function () {
    $item = p25Barang('Proyektor', 10);

    $loan = $this->layanan->buat(p25Data($item, 4, [
        'tanggal_pinjam' => p25Tgl(3),
        'batas_kembali' => p25Tgl(5),
    ]));

    $item->refresh();
    expect([$item->total, $item->tersedia, $item->rusak])->toBe([10, 6, 0]);

    $status = app(StatusPeminjaman::class)->dariModel($loan, Tanggal::hariIni());
    expect($status['status'])->toBe(StatusPeminjaman::DIPESAN);

    expect(StockMovement::where('item_id', $item->id)->where('jenis', 'PINJAM')->count())->toBe(1);
    expect(AuditLog::where('aksi', 'buat_peminjaman')->firstOrFail()->detail['pemesanan'])->toBeTrue();
});

it('peminjaman hari ini tidak berstatus dipesan (T-23)', function () {
    $item = p25Barang('Proyektor', 10);

    $loan = $this->layanan->buat(p25Data($item, 2));

    $status = app(StatusPeminjaman::class)->dariModel($loan, Tanggal::hariIni());
    expect($status['status'])->toBe(StatusPeminjaman::DIPINJAM);
    expect(AuditLog::where('aksi', 'buat_peminjaman')->firstOrFail()->detail['pemesanan'])->toBeFalse();
});

it('pemesanan tepat di batas maks_booking_hari diterima (T-23)', function () {
    $item = p25Barang('Proyektor', 10);

    $loan = $this->layanan->buat(p25Data($item, 1, [
        'tanggal_pinjam' => p25Tgl(7),
        'batas_kembali' => p25Tgl(8),
    ]));

    expect($loan->tanggal_pinjam->format('Y-m-d'))->toBe(p25Tgl(7));
    expect($item->fresh()->tersedia)->toBe(9);
});

it('pemesanan melebihi stok ditolak 409 tanpa efek (T-23)', function () {
    $item = p25Barang('Proyektor', 10);

    $e = p25Tangkap(fn () => $this->layanan->buat(p25Data($item, 11, [
        'tanggal_pinjam' => p25Tgl(2),
        'batas_kembali' => p25Tgl(4),
    ])));

    expect($e)->toBeInstanceOf(GalatBisnis::class)
        ->and($e->getStatusCode())->toBe(409);
    expect($item->fresh()->tersedia)->toBe(10);
    p25TidakAdaEfek();
});
