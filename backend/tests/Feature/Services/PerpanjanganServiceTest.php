<?php

use App\Exceptions\GalatBisnis;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Division;
use App\Models\Item;
use App\Models\Loan;
use App\Models\LoanExtension;
use App\Models\Setting;
use App\Models\User;
use App\Services\KlaimService;
use App\Services\PeminjamanService;
use App\Services\PerpanjanganService;
use App\Support\Tanggal;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SettingSeeder::class);
    $this->perpanjangan = app(PerpanjanganService::class);
});

function perpanjanganTanggal(int $offset = 0): string
{
    return (new DateTimeImmutable(Tanggal::hariIni(), new DateTimeZone('Asia/Jakarta')))
        ->modify("{$offset} days")->format('Y-m-d');
}

/** @return array{0: Loan, 1: Item} */
function perpanjanganPinjam(
    int $tanggalPinjamOffset = 0,
    int $batasKembaliOffset = 3,
    int $qty = 3,
    bool $bolehDiperpanjang = true,
): array {
    $kategori = Category::firstOrCreate(['nama' => 'Kategori Perpanjangan'], ['aktif' => true]);
    $item = Item::create([
        'nama' => 'Laptop Uji '.Str::uuid(),
        'kategori_id' => $kategori->id,
        'total' => 10,
        'tersedia' => 10,
        'rusak' => 0,
        'boleh_diperpanjang' => $bolehDiperpanjang,
    ]);

    $loan = app(PeminjamanService::class)->buat([
        'nama_peminjam' => 'Budi Santoso',
        'division_id' => Division::firstOrCreate(['nama' => 'IT'], ['aktif' => true])->id,
        'tipe_peminjam' => 'karyawan',
        'telepon' => '081234567890',
        'tanggal_pinjam' => perpanjanganTanggal($tanggalPinjamOffset),
        'batas_kembali' => perpanjanganTanggal($batasKembaliOffset),
        'idempotency_key' => (string) Str::uuid(),
        'items' => [['item_id' => $item->id, 'qty' => $qty]],
    ]);

    return [Loan::findOrFail($loan->id), $item->refresh()];
}

function perpanjanganAdmin(): User
{
    return User::create([
        'name' => 'Admin Perpanjangan',
        'email' => 'admin-perpanjangan@example.test',
        'password' => 'sandi-uji-panjang-123',
    ]);
}

it('memperpanjang peminjaman peminjam dari batas lama dan mencatat riwayat (T-17)', function () {
    [$loan, $item] = perpanjanganPinjam(batasKembaliOffset: 3);

    $hasil = $this->perpanjangan->olehPeminjam($loan->kode_pinjam, 2);

    expect($hasil->batas_kembali->format('Y-m-d'))->toBe(perpanjanganTanggal(5))
        ->and(LoanExtension::count())->toBe(1)
        ->and(LoanExtension::first()->batas_lama->format('Y-m-d'))->toBe(perpanjanganTanggal(3))
        ->and(LoanExtension::first()->batas_baru->format('Y-m-d'))->toBe(perpanjanganTanggal(5))
        ->and(LoanExtension::first()->diajukan_oleh)->toBe('peminjam')
        ->and(LoanExtension::first()->user_id)->toBeNull()
        ->and(AuditLog::where('aksi', 'perpanjang_peminjaman')->count())->toBe(1)
        ->and($item->fresh()->tersedia)->toBe(7);
});

it('menolak peminjam yang terlambat, kehabisan kuota, atau meminta hari di luar batas (T-18)', function () {
    [$loan, $item] = perpanjanganPinjam();
    $loan->update([
        'tanggal_pinjam' => perpanjanganTanggal(-8),
        'batas_kembali' => perpanjanganTanggal(-1),
    ]);
    expect(fn () => $this->perpanjangan->olehPeminjam($loan->kode_pinjam, 1))
        ->toThrow(GalatBisnis::class);

    [$loanKuota] = perpanjanganPinjam();
    Setting::whereKey('maks_perpanjangan')->update(['nilai' => '0']);
    expect(fn () => $this->perpanjangan->olehPeminjam($loanKuota->kode_pinjam, 1))
        ->toThrow(GalatBisnis::class);

    Setting::whereKey('maks_perpanjangan')->update(['nilai' => '2']);
    [$loanHari] = perpanjanganPinjam();
    expect(fn () => $this->perpanjangan->olehPeminjam($loanHari->kode_pinjam, 8))
        ->toThrow(ValidationException::class);

    expect(LoanExtension::count())->toBe(0)
        ->and($item->fresh()->tersedia)->toBe(7);
});

it('menolak perpanjangan peminjam saat tidak ada unit tersisa atau peminjaman belum dimulai', function () {
    [$loan, $item] = perpanjanganPinjam();
    app(KlaimService::class)->buat([
        'kode_pinjam' => $loan->kode_pinjam,
        'idempotency_key' => (string) Str::uuid(),
        'baris' => [['item_id' => $item->id, 'baik' => 3, 'rusak' => 0, 'hilang' => 0]],
    ]);

    expect(fn () => $this->perpanjangan->olehPeminjam($loan->kode_pinjam, 1))
        ->toThrow(GalatBisnis::class);

    [$booking] = perpanjanganPinjam(tanggalPinjamOffset: 2, batasKembaliOffset: 5);
    expect(fn () => $this->perpanjangan->olehPeminjam($booking->kode_pinjam, 1))
        ->toThrow(GalatBisnis::class);

    expect(LoanExtension::count())->toBe(0);
});

it('menolak peminjam jika barang yang masih dipinjam tidak boleh diperpanjang dan menyebut namanya (T-27)', function () {
    [$loan] = perpanjanganPinjam(bolehDiperpanjang: false);

    try {
        $this->perpanjangan->olehPeminjam($loan->kode_pinjam, 1);
        test()->fail('Seharusnya perpanjangan ditolak.');
    } catch (GalatBisnis $e) {
        expect($e->getMessage())->toContain('Laptop Uji');
    }

    expect(LoanExtension::count())->toBe(0);
});

it('admin dapat memperpanjang peminjaman terlambat dan barang yang tidak boleh diperpanjang (T-19, T-27)', function () {
    $admin = perpanjanganAdmin();
    [$loan, $item] = perpanjanganPinjam(bolehDiperpanjang: false);
    $loan->update([
        'tanggal_pinjam' => perpanjanganTanggal(-8),
        'batas_kembali' => perpanjanganTanggal(-2),
    ]);

    $hasil = $this->perpanjangan->olehAdmin($loan->id, 3, $admin->id, 'Persetujuan khusus');

    expect($hasil->batas_kembali->format('Y-m-d'))->toBe(perpanjanganTanggal(1))
        ->and(LoanExtension::count())->toBe(1)
        ->and(LoanExtension::first()->diajukan_oleh)->toBe('admin')
        ->and(LoanExtension::first()->user_id)->toBe($admin->id)
        ->and(LoanExtension::first()->alasan)->toBe('Persetujuan khusus')
        ->and(AuditLog::where('aksi', 'perpanjang_peminjaman')->first()->user_id)->toBe($admin->id)
        ->and($item->fresh()->tersedia)->toBe(7);
});

it('menolak perpanjangan peminjaman yang sudah dibatalkan', function () {
    $admin = perpanjanganAdmin();
    [$loan] = perpanjanganPinjam();
    $loan->update(['dibatalkan_pada' => now('UTC')]);

    expect(fn () => $this->perpanjangan->olehPeminjam($loan->kode_pinjam, 1))
        ->toThrow(GalatBisnis::class);
    expect(fn () => $this->perpanjangan->olehAdmin($loan->id, 1, $admin->id, 'Alasan'))
        ->toThrow(GalatBisnis::class);
    expect(LoanExtension::count())->toBe(0);
});

it('admin wajib memberi alasan dan peminjaman batal atau pemesanan tidak dapat diperpanjang', function (string $alasan) {
    $admin = perpanjanganAdmin();
    [$loan] = perpanjanganPinjam();

    expect(fn () => $this->perpanjangan->olehAdmin($loan->id, 1, $admin->id, $alasan))
        ->toThrow(ValidationException::class);

    [$booking] = perpanjanganPinjam(tanggalPinjamOffset: 2, batasKembaliOffset: 5);
    expect(fn () => $this->perpanjangan->olehAdmin($booking->id, 1, $admin->id, 'Alasan'))
        ->toThrow(GalatBisnis::class);
})->with(['kosong' => '', 'spasi' => '   ', 'terlalu panjang' => str_repeat('a', 501)]);

it('kuota habis setelah perpanjangan sebanyak maks_perpanjangan (T-18)', function () {
    [$loan] = perpanjanganPinjam(batasKembaliOffset: 3);

    $this->perpanjangan->olehPeminjam($loan->kode_pinjam, 1);
    $this->perpanjangan->olehPeminjam($loan->kode_pinjam, 1);

    expect(fn () => $this->perpanjangan->olehPeminjam($loan->kode_pinjam, 1))
        ->toThrow(GalatBisnis::class);

    expect(LoanExtension::count())->toBe(2)
        ->and(Loan::find($loan->id)->batas_kembali->format('Y-m-d'))->toBe(perpanjanganTanggal(5));
});

it('perpanjangan berturut-turut dihitung dari batas kembali saat ini, bukan dari hari ini (BR-12)', function () {
    [$loan] = perpanjanganPinjam(batasKembaliOffset: 3);

    $this->perpanjangan->olehPeminjam($loan->kode_pinjam, 2);
    $hasil = $this->perpanjangan->olehPeminjam($loan->kode_pinjam, 3);

    expect($hasil->batas_kembali->format('Y-m-d'))->toBe(perpanjanganTanggal(8));
    expect(LoanExtension::orderBy('id')->get()->map(fn ($e) => $e->batas_lama->format('Y-m-d'))->all())
        ->toBe([perpanjanganTanggal(3), perpanjanganTanggal(5)]);
});

it('admin tidak dibatasi kuota perpanjangan (BR-13)', function () {
    [$loan] = perpanjanganPinjam(batasKembaliOffset: 3);
    $admin = perpanjanganAdmin();

    foreach ([1, 2, 3] as $ke) {
        $this->perpanjangan->olehAdmin($loan->id, 1, $admin->id, "Perpanjangan ke-{$ke}");
    }

    expect(LoanExtension::where('diajukan_oleh', 'admin')->count())->toBe(3)
        ->and(Loan::find($loan->id)->batas_kembali->format('Y-m-d'))->toBe(perpanjanganTanggal(6));
});
