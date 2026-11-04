<?php

use App\Exceptions\GalatBisnis;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Division;
use App\Models\Item;
use App\Models\Loan;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\KlaimService;
use App\Services\PembatalanService;
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
    $this->pembatalan = app(PembatalanService::class);
});

function pembatalanTanggal(int $offset = 0): string
{
    return (new DateTimeImmutable(Tanggal::hariIni(), new DateTimeZone('Asia/Jakarta')))
        ->modify("{$offset} days")->format('Y-m-d');
}

function pembatalanBarang(string $nama = 'Laptop Uji', int $stok = 10): Item
{
    $kategori = Category::firstOrCreate(['nama' => 'Kategori Uji'], ['aktif' => true]);

    return Item::create([
        'nama' => $nama,
        'kategori_id' => $kategori->id,
        'total' => $stok,
        'tersedia' => $stok,
        'rusak' => 0,
        'boleh_diperpanjang' => true,
    ]);
}

function pembatalanPinjam(Item $item, int $tanggalPinjamOffset = 0, int $qty = 2): Loan
{
    $tanggalPinjam = pembatalanTanggal($tanggalPinjamOffset);

    return app(PeminjamanService::class)->buat([
        'nama_peminjam' => 'Budi Santoso',
        'division_id' => Division::firstOrCreate(['nama' => 'IT'], ['aktif' => true])->id,
        'tipe_peminjam' => 'karyawan',
        'telepon' => '081234567890',
        'tanggal_pinjam' => $tanggalPinjam,
        'batas_kembali' => pembatalanTanggal($tanggalPinjamOffset + 3),
        'idempotency_key' => (string) Str::uuid(),
        'items' => [['item_id' => $item->id, 'qty' => $qty]],
    ]);
}

function pembatalanAdmin(): User
{
    return User::create([
        'name' => 'Admin Uji',
        'email' => 'admin-pembatalan@example.test',
        'password' => 'sandi-uji-panjang-123',
    ]);
}

it('membatalkan pemesanan peminjam sebelum tanggal pinjam dan mengembalikan stok satu kali (T-24)', function () {
    $item = pembatalanBarang();
    $loan = pembatalanPinjam($item, 2, 3);

    $hasil = $this->pembatalan->olehPeminjam($loan->kode_pinjam);

    expect($hasil->dibatalkan_pada)->not->toBeNull()
        ->and($hasil->alasan_batal)->toBeNull()
        ->and($hasil->dibatalkan_oleh)->toBeNull()
        ->and(app(StatusPeminjaman::class)->dariModel($hasil, Tanggal::hariIni())['status'])
        ->toBe(StatusPeminjaman::DIBATALKAN)
        ->and($item->fresh()->tersedia)->toBe(10)
        ->and(StockMovement::where('item_id', $item->id)->where('jenis', 'BATAL_PINJAM')->count())->toBe(1)
        ->and(StockMovement::where('item_id', $item->id)->where('jenis', 'BATAL_PINJAM')->first()->delta_tersedia)->toBe(3)
        ->and(AuditLog::where('aksi', 'batalkan_peminjaman')->count())->toBe(1);
    expect(json_encode(AuditLog::where('aksi', 'batalkan_peminjaman')->firstOrFail()->detail))
        ->not->toContain($loan->kode_pinjam);

    expect(fn () => $this->pembatalan->olehPeminjam($loan->kode_pinjam))
        ->toThrow(GalatBisnis::class);
    expect(StockMovement::where('jenis', 'BATAL_PINJAM')->count())->toBe(1)
        ->and($item->fresh()->tersedia)->toBe(10);
});

it('menolak pembatalan peminjam pada atau setelah tanggal pinjam tanpa efek (T-24)', function () {
    $item = pembatalanBarang();
    $loan = pembatalanPinjam($item);

    expect(fn () => $this->pembatalan->olehPeminjam($loan->kode_pinjam))
        ->toThrow(GalatBisnis::class);

    expect($loan->fresh()->dibatalkan_pada)->toBeNull()
        ->and($item->fresh()->tersedia)->toBe(8)
        ->and(StockMovement::where('jenis', 'BATAL_PINJAM')->count())->toBe(0)
        ->and(AuditLog::where('aksi', 'batalkan_peminjaman')->count())->toBe(0);
});

it('membatalkan oleh admin dengan alasan, aktor, mutasi, dan audit (T-26)', function () {
    $admin = pembatalanAdmin();
    $item = pembatalanBarang();
    $loan = pembatalanPinjam($item, 2, 2);

    $hasil = $this->pembatalan->olehAdmin($loan->id, $admin->id, 'Pemesanan duplikat');

    expect($hasil->dibatalkan_oleh)->toBe($admin->id)
        ->and($hasil->alasan_batal)->toBe('Pemesanan duplikat')
        ->and($item->fresh()->tersedia)->toBe(10)
        ->and(StockMovement::where('jenis', 'BATAL_PINJAM')->first()->user_id)->toBe($admin->id)
        ->and(StockMovement::where('jenis', 'BATAL_PINJAM')->first()->catatan)->toBe('Pemesanan duplikat')
        ->and(AuditLog::where('aksi', 'batalkan_peminjaman')->first()->user_id)->toBe($admin->id);
});

it('menolak pembatalan admin tanpa alasan yang valid', function (string $alasan) {
    $admin = pembatalanAdmin();
    $item = pembatalanBarang();
    $loan = pembatalanPinjam($item, 2);

    expect(fn () => $this->pembatalan->olehAdmin($loan->id, $admin->id, $alasan))
        ->toThrow(ValidationException::class);

    expect($loan->fresh()->dibatalkan_pada)->toBeNull()
        ->and($item->fresh()->tersedia)->toBe(8)
        ->and(StockMovement::where('jenis', 'BATAL_PINJAM')->count())->toBe(0);
})->with(['kosong' => '', 'spasi' => '   ', 'terlalu panjang' => str_repeat('a', 501)]);

it('menolak pembatalan admin bila peminjaman sudah memiliki klaim (T-26)', function () {
    $admin = pembatalanAdmin();
    $item = pembatalanBarang();
    $loan = pembatalanPinjam($item);

    app(KlaimService::class)->buat([
        'kode_pinjam' => $loan->kode_pinjam,
        'idempotency_key' => (string) Str::uuid(),
        'baris' => [['item_id' => $item->id, 'baik' => 2, 'rusak' => 0, 'hilang' => 0]],
    ]);

    expect(fn () => $this->pembatalan->olehAdmin($loan->id, $admin->id, 'Pembatalan admin'))
        ->toThrow(GalatBisnis::class);

    expect($loan->fresh()->dibatalkan_pada)->toBeNull()
        ->and($item->fresh()->tersedia)->toBe(8)
        ->and(StockMovement::where('jenis', 'BATAL_PINJAM')->count())->toBe(0)
        ->and(AuditLog::where('aksi', 'batalkan_peminjaman')->count())->toBe(0);
});
