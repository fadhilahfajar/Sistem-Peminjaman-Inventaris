<?php

use App\Exceptions\GalatBisnis;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Division;
use App\Models\Item;
use App\Models\Loan;
use App\Models\LoanCorrection;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\KlaimService;
use App\Services\KoreksiService;
use App\Services\PeminjamanService;
use App\Support\BukuBesarStok;
use App\Support\Tanggal;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SettingSeeder::class);
    $this->koreksi = app(KoreksiService::class);
});

function koreksiTanggal(int $offset = 0): string
{
    return (new DateTimeImmutable(Tanggal::hariIni(), new DateTimeZone('Asia/Jakarta')))
        ->modify("{$offset} days")->format('Y-m-d');
}

/** @return array{0: Loan, 1: Item, 2: Division} */
function buatPeminjamanUji(int $qty = 3, int $stokTersedia = 10): array
{
    $divisi = Division::firstOrCreate(['nama' => 'IT'], ['aktif' => true]);
    $kategori = Category::firstOrCreate(['nama' => 'Hardware'], ['aktif' => true]);

    $item = Item::create([
        'nama' => 'Proyektor Uji '.Str::uuid(),
        'kategori_id' => $kategori->id,
        'total' => $stokTersedia,
        'tersedia' => $stokTersedia,
        'rusak' => 0,
        'boleh_diperpanjang' => true,
    ]);
    BukuBesarStok::catat($item, 'TAMBAH_STOK', $stokTersedia, $stokTersedia, 0, 'items', $item->id);

    $loan = app(PeminjamanService::class)->buat([
        'nama_peminjam' => 'Ahmad Dahlan',
        'division_id' => $divisi->id,
        'tipe_peminjam' => 'karyawan',
        'telepon' => '081234567890',
        'tanggal_pinjam' => koreksiTanggal(0),
        'batas_kembali' => koreksiTanggal(3),
        'idempotency_key' => (string) Str::uuid(),
        'items' => [['item_id' => $item->id, 'qty' => $qty]],
    ]);

    return [Loan::findOrFail($loan->id), $item->refresh(), $divisi];
}

function buatAdminUji(): User
{
    return User::create([
        'name' => 'Admin Koreksi',
        'email' => 'admin-koreksi-'.Str::uuid().'@example.test',
        'password' => 'sandi-rahasia-admin-123',
        'aktif' => true,
    ]);
}

it('mengoreksi jumlah pinjam bertambah: stok tersedia berkurang, mutasi KOREKSI_PINJAM, dan riwayat tersimpan (BR-16)', function () {
    [$loan, $item] = buatPeminjamanUji(qty: 3, stokTersedia: 10);
    $admin = buatAdminUji();

    // Stok tersedia awal 10 - 3 = 7
    expect($item->fresh()->tersedia)->toBe(7);

    $hasil = $this->koreksi->koreksi(
        loanId: $loan->id,
        userId: $admin->id,
        data: [
            'items' => [['item_id' => $item->id, 'qty' => 5]],
        ],
        alasan: 'Peminjam menambah 2 unit lagi',
    );

    // qty_dipinjam menjadi 5, stok tersedia menjadi 7 - 2 = 5
    expect($hasil->items()->where('item_id', $item->id)->first()->qty_dipinjam)->toBe(5)
        ->and($item->fresh()->tersedia)->toBe(5);

    // Riwayat di loan_corrections
    $koreksi = LoanCorrection::where('loan_id', $loan->id)->first();
    expect($koreksi)->not->toBeNull()
        ->and($koreksi->kolom)->toBe("qty_dipinjam:{$item->id}")
        ->and($koreksi->nilai_lama)->toBe('3')
        ->and($koreksi->nilai_baru)->toBe('5')
        ->and($koreksi->alasan)->toBe('Peminjam menambah 2 unit lagi')
        ->and($koreksi->user_id)->toBe($admin->id);

    // Mutasi stok KOREKSI_PINJAM: delta_total=0, delta_tersedia=-2, delta_rusak=0
    $mutasi = StockMovement::where('item_id', $item->id)->latest('id')->first();
    expect($mutasi->jenis)->toBe('KOREKSI_PINJAM')
        ->and($mutasi->delta_total)->toBe(0)
        ->and($mutasi->delta_tersedia)->toBe(-2)
        ->and($mutasi->delta_rusak)->toBe(0)
        ->and($mutasi->user_id)->toBe($admin->id);

    // Audit log
    expect(AuditLog::where('aksi', 'koreksi_peminjaman')->count())->toBe(1);
    assertInvarian();
});

it('mengoreksi jumlah pinjam berkurang: stok tersedia bertambah, mutasi KOREKSI_PINJAM, dan riwayat tersimpan (BR-16)', function () {
    [$loan, $item] = buatPeminjamanUji(qty: 4, stokTersedia: 10);
    $admin = buatAdminUji();

    // Stok tersedia awal 10 - 4 = 6
    expect($item->fresh()->tersedia)->toBe(6);

    $hasil = $this->koreksi->koreksi(
        loanId: $loan->id,
        userId: $admin->id,
        data: [
            'items' => [['item_id' => $item->id, 'qty' => 2]],
        ],
        alasan: 'Kelebihan input saat pinjam',
    );

    // qty_dipinjam menjadi 2, stok tersedia menjadi 6 + 2 = 8
    expect($hasil->items()->where('item_id', $item->id)->first()->qty_dipinjam)->toBe(2)
        ->and($item->fresh()->tersedia)->toBe(8);

    // Riwayat di loan_corrections
    $koreksi = LoanCorrection::where('loan_id', $loan->id)->first();
    expect($koreksi)->not->toBeNull()
        ->and($koreksi->kolom)->toBe("qty_dipinjam:{$item->id}")
        ->and($koreksi->nilai_lama)->toBe('4')
        ->and($koreksi->nilai_baru)->toBe('2');

    // Mutasi stok KOREKSI_PINJAM: delta_total=0, delta_tersedia=2, delta_rusak=0
    $mutasi = StockMovement::where('item_id', $item->id)->latest('id')->first();
    expect($mutasi->jenis)->toBe('KOREKSI_PINJAM')
        ->and($mutasi->delta_total)->toBe(0)
        ->and($mutasi->delta_tersedia)->toBe(2)
        ->and($mutasi->delta_rusak)->toBe(0);

    assertInvarian();
});

it('menolak koreksi jumlah jika stok tidak mencukupi untuk penambahan', function () {
    [$loan, $item] = buatPeminjamanUji(qty: 3, stokTersedia: 5);
    $admin = buatAdminUji();

    // Sisa tersedia = 2. Minta tambah menjadi 7 (butuh 4 lagi) -> harus ditolak
    expect(fn () => $this->koreksi->koreksi(
        loanId: $loan->id,
        userId: $admin->id,
        data: ['items' => [['item_id' => $item->id, 'qty' => 7]]],
        alasan: 'Minta tambah',
    ))->toThrow(GalatBisnis::class);

    // Stok tidak berubah
    expect($item->fresh()->tersedia)->toBe(2)
        ->and(LoanCorrection::count())->toBe(0);

    assertInvarian();
});

it('menolak koreksi jumlah jika barang sudah memiliki klaim (BR-16)', function () {
    [$loan, $item] = buatPeminjamanUji(qty: 3, stokTersedia: 10);
    $admin = buatAdminUji();

    // Buat klaim atas barang tersebut
    app(KlaimService::class)->buat([
        'kode_pinjam' => $loan->kode_pinjam,
        'idempotency_key' => (string) Str::uuid(),
        'baris' => [['item_id' => $item->id, 'baik' => 1, 'rusak' => 0, 'hilang' => 0]],
    ]);

    expect(fn () => $this->koreksi->koreksi(
        loanId: $loan->id,
        userId: $admin->id,
        data: ['items' => [['item_id' => $item->id, 'qty' => 4]]],
        alasan: 'Coba ubah jumlah barang yang sudah diklaim',
    ))->toThrow(GalatBisnis::class);

    expect(LoanCorrection::count())->toBe(0);
    assertInvarian();
});

it('mengoreksi data header: nama, divisi, telepon, tanggal pinjam, dan batas kembali', function () {
    [$loan, $item, $divisiLama] = buatPeminjamanUji(qty: 2, stokTersedia: 5);
    $admin = buatAdminUji();
    $divisiBaru = Division::create(['nama' => 'Media & Kreatif', 'aktif' => true]);

    $tglPinjamBaru = koreksiTanggal(1);
    $batasBaru = koreksiTanggal(5);

    $hasil = $this->koreksi->koreksi(
        loanId: $loan->id,
        userId: $admin->id,
        data: [
            'nama_peminjam' => 'Bambang Sudarmono',
            'division_id' => $divisiBaru->id,
            'telepon' => '08987654321',
            'tanggal_pinjam' => $tglPinjamBaru,
            'batas_kembali' => $batasBaru,
        ],
        alasan: 'Koreksi salah input administrasi',
    );

    expect($hasil->nama_peminjam)->toBe('Bambang Sudarmono')
        ->and($hasil->division_id)->toBe($divisiBaru->id)
        ->and($hasil->telepon)->toBe('08987654321')
        ->and($hasil->tanggal_pinjam->format('Y-m-d'))->toBe($tglPinjamBaru)
        ->and($hasil->batas_kembali->format('Y-m-d'))->toBe($batasBaru);

    // Memeriksa 5 riwayat tercatat di loan_corrections
    $riwayat = LoanCorrection::where('loan_id', $loan->id)->pluck('kolom')->all();
    expect($riwayat)->toContain('nama_peminjam')
        ->toContain('division_id')
        ->toContain('telepon')
        ->toContain('tanggal_pinjam')
        ->toContain('batas_kembali')
        ->toHaveCount(5);

    assertInvarian();
});

it('menolak koreksi jika peminjaman sudah dibatalkan', function () {
    [$loan] = buatPeminjamanUji();
    $admin = buatAdminUji();

    $loan->update(['dibatalkan_pada' => now('UTC')]);

    expect(fn () => $this->koreksi->koreksi(
        loanId: $loan->id,
        userId: $admin->id,
        data: ['nama_peminjam' => 'Nama Baru'],
        alasan: 'Ubah peminjaman batal',
    ))->toThrow(GalatBisnis::class);

    expect(LoanCorrection::count())->toBe(0);
});

it('menolak koreksi tanpa alasan atau alasan tidak sah', function (string $alasan) {
    [$loan] = buatPeminjamanUji();
    $admin = buatAdminUji();

    expect(fn () => $this->koreksi->koreksi(
        loanId: $loan->id,
        userId: $admin->id,
        data: ['nama_peminjam' => 'Nama Baru'],
        alasan: $alasan,
    ))->toThrow(ValidationException::class);
})->with([
    'kosong' => '',
    'spasi' => '   ',
    'terlalu panjang' => str_repeat('k', 501),
]);

it('menolak koreksi jika batas kembali sebelum tanggal pinjam', function () {
    [$loan] = buatPeminjamanUji();
    $admin = buatAdminUji();

    expect(fn () => $this->koreksi->koreksi(
        loanId: $loan->id,
        userId: $admin->id,
        data: [
            'tanggal_pinjam' => koreksiTanggal(5),
            'batas_kembali' => koreksiTanggal(2),
        ],
        alasan: 'Tanggal terbalik',
    ))->toThrow(ValidationException::class);
});

it('menolak koreksi jika tidak ada data yang berubah', function () {
    [$loan] = buatPeminjamanUji();
    $admin = buatAdminUji();

    expect(fn () => $this->koreksi->koreksi(
        loanId: $loan->id,
        userId: $admin->id,
        data: [
            'nama_peminjam' => $loan->nama_peminjam,
        ],
        alasan: 'Tidak ada yang diubah',
    ))->toThrow(ValidationException::class);

    expect(LoanCorrection::count())->toBe(0);
});
