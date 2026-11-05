<?php

use App\Models\Category;
use App\Models\Division;
use App\Models\Item;
use App\Models\Loan;
use App\Models\ReturnClaim;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\KlaimService;
use App\Services\PeminjamanService;
use App\Support\BukuBesarStok;
use App\Support\Tanggal;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

afterEach(function () {
    // Bersihkan data yang di-commit selama tes konkurensi
    DB::table('audit_logs')->delete();
    DB::table('loan_corrections')->delete();
    DB::table('loan_extensions')->delete();
    DB::table('return_claim_lines')->delete();
    DB::table('return_claims')->delete();
    DB::table('loan_items')->delete();
    DB::table('loans')->delete();
    DB::table('stock_movements')->delete();
    DB::table('items')->delete();
    DB::table('divisions')->delete();
    DB::table('categories')->delete();
    DB::table('users')->delete();
    DB::table('settings')->delete();

    // Pastikan transaksi kembali aktif agar RefreshDatabase tidak error saat tearDown
    if (DB::transactionLevel() === 0) {
        DB::beginTransaction();
    }
});

/**
 * Menjalankan dua proses terpisah secara konkuren menggunakan penghalang berkas (flock).
 *
 * @param  array<string, mixed>  $payload1
 * @param  array<string, mixed>  $payload2
 * @return array{
 *     p1: array{kode: int, output: ?array<string, mixed>, raw: string, err: string},
 *     p2: array{kode: int, output: ?array<string, mixed>, raw: string, err: string}
 * }
 */
function jalankanDuaProsesKonkuren(string $jenis, array $payload1, array $payload2): array
{
    $tempDir = sys_get_temp_dir();
    $barrierFile = $tempDir.'/barrier_'.uniqid('', true).'.lock';
    $readyFile1 = $tempDir.'/ready1_'.uniqid('', true).'.tmp';
    $readyFile2 = $tempDir.'/ready2_'.uniqid('', true).'.tmp';

    $barrierFp = fopen($barrierFile, 'c+');
    flock($barrierFp, LOCK_EX);

    $phpBinary = PHP_BINARY;
    $script = base_path('tests/Workers/PekerjaKonkurensi.php');

    $cmd1 = [
        $phpBinary,
        $script,
        $jenis,
        json_encode($payload1),
        $barrierFile,
        $readyFile1,
    ];

    $cmd2 = [
        $phpBinary,
        $script,
        $jenis,
        json_encode($payload2),
        $barrierFile,
        $readyFile2,
    ];

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $p1 = proc_open($cmd1, $descriptors, $pipes1, base_path());
    $p2 = proc_open($cmd2, $descriptors, $pipes2, base_path());

    // Tunggu hingga kedua proses siap dan terhenti pada berkas penghalang (maks 10 detik)
    $mulai = microtime(true);
    while ((! file_exists($readyFile1) || ! file_exists($readyFile2)) && (microtime(true) - $mulai < 10.0)) {
        usleep(10000); // 10ms
    }

    // Lepas kunci penghalang secara serentak
    flock($barrierFp, LOCK_UN);
    fclose($barrierFp);

    $out1 = stream_get_contents($pipes1[1]);
    $err1 = stream_get_contents($pipes1[2]);
    fclose($pipes1[0]);
    fclose($pipes1[1]);
    fclose($pipes1[2]);
    $code1 = proc_close($p1);

    $out2 = stream_get_contents($pipes2[1]);
    $err2 = stream_get_contents($pipes2[2]);
    fclose($pipes2[0]);
    fclose($pipes2[1]);
    fclose($pipes2[2]);
    $code2 = proc_close($p2);

    @unlink($barrierFile);
    @unlink($readyFile1);
    @unlink($readyFile2);

    return [
        'p1' => [
            'kode' => $code1,
            'output' => json_decode($out1, true),
            'raw' => $out1,
            'err' => $err1,
        ],
        'p2' => [
            'kode' => $code2,
            'output' => json_decode($out2, true),
            'raw' => $out2,
            'err' => $err2,
        ],
    ];
}

it('dua peminjaman bersamaan dari proses terpisah untuk stok 1: tepat satu berhasil dan satu gagal 409 (T-21)', function () {
    $this->seed(SettingSeeder::class);

    $divisi = Division::create(['nama' => 'IT Konkurensi', 'aktif' => true]);
    $kategori = Category::create(['nama' => 'Hardware Konkurensi', 'aktif' => true]);

    $item = Item::create([
        'nama' => 'Laptop Terbatas '.Str::uuid(),
        'kategori_id' => $kategori->id,
        'total' => 1,
        'tersedia' => 1,
        'rusak' => 0,
        'boleh_diperpanjang' => true,
    ]);
    BukuBesarStok::catat($item, 'TAMBAH_STOK', 1, 1, 0, 'items', $item->id);

    // Commit agar proses anak yang berjalan di koneksi DB lain dapat melihat data ini
    DB::commit();

    $hariIni = Tanggal::hariIni();
    $batasKembali = (new DateTimeImmutable($hariIni, new DateTimeZone('Asia/Jakarta')))
        ->modify('+3 days')->format('Y-m-d');

    $payload1 = [
        'nama_peminjam' => 'Peminjam Pertama',
        'division_id' => $divisi->id,
        'tipe_peminjam' => 'karyawan',
        'telepon' => '081200000001',
        'tanggal_pinjam' => $hariIni,
        'batas_kembali' => $batasKembali,
        'idempotency_key' => (string) Str::uuid(),
        'items' => [['item_id' => $item->id, 'qty' => 1]],
    ];

    $payload2 = [
        'nama_peminjam' => 'Peminjam Kedua',
        'division_id' => $divisi->id,
        'tipe_peminjam' => 'karyawan',
        'telepon' => '081200000002',
        'tanggal_pinjam' => $hariIni,
        'batas_kembali' => $batasKembali,
        'idempotency_key' => (string) Str::uuid(),
        'items' => [['item_id' => $item->id, 'qty' => 1]],
    ];

    $hasil = jalankanDuaProsesKonkuren('pinjam', $payload1, $payload2);

    $p1 = $hasil['p1'];
    $p2 = $hasil['p2'];

    $sukses = 0;
    $gagalKonflik = 0;

    foreach ([$p1, $p2] as $p) {
        if ($p['kode'] === 0 && ($p['output']['sukses'] ?? false) === true) {
            $sukses++;
        } elseif ($p['kode'] === 1 && ($p['output']['status'] ?? null) === 409) {
            $gagalKonflik++;
        }
    }

    expect($sukses)->toBe(1)
        ->and($gagalKonflik)->toBe(1);

    // Verifikasi kondisi database: stok tersedia menjadi 0, peminjaman tercatat 1 baris
    $itemSegar = Item::find($item->id);
    expect($itemSegar->tersedia)->toBe(0)
        ->and($itemSegar->total)->toBe(1)
        ->and(Loan::count())->toBe(1)
        ->and(StockMovement::where('item_id', $item->id)->where('jenis', 'PINJAM')->count())->toBe(1);

    assertInvarian();
});

it('dua verifikasi paralel pada satu klaim: satu berhasil dan satu 409 (T-22)', function () {
    $this->seed(SettingSeeder::class);

    $admin = User::create([
        'name' => 'Admin Verifikasi',
        'email' => 'admin-verif-'.Str::uuid().'@example.test',
        'password' => 'password-aman-12345',
        'aktif' => true,
    ]);

    $divisi = Division::create(['nama' => 'IT Verif', 'aktif' => true]);
    $kategori = Category::create(['nama' => 'Hardware Verif', 'aktif' => true]);

    $item = Item::create([
        'nama' => 'Proyektor Verif '.Str::uuid(),
        'kategori_id' => $kategori->id,
        'total' => 2,
        'tersedia' => 2,
        'rusak' => 0,
        'boleh_diperpanjang' => true,
    ]);
    BukuBesarStok::catat($item, 'TAMBAH_STOK', 2, 2, 0, 'items', $item->id);

    $hariIni = Tanggal::hariIni();
    $batasKembali = (new DateTimeImmutable($hariIni, new DateTimeZone('Asia/Jakarta')))
        ->modify('+3 days')->format('Y-m-d');

    // Buat peminjaman 2 unit
    $loan = app(PeminjamanService::class)->buat([
        'nama_peminjam' => 'Peminjam Verif',
        'division_id' => $divisi->id,
        'tipe_peminjam' => 'karyawan',
        'telepon' => '081234567890',
        'tanggal_pinjam' => $hariIni,
        'batas_kembali' => $batasKembali,
        'idempotency_key' => (string) Str::uuid(),
        'items' => [['item_id' => $item->id, 'qty' => 2]],
    ]);

    // Buat klaim 2 unit
    $klaim = app(KlaimService::class)->buat([
        'kode_pinjam' => $loan->kode_pinjam,
        'idempotency_key' => (string) Str::uuid(),
        'baris' => [['item_id' => $item->id, 'baik' => 2, 'rusak' => 0, 'hilang' => 0]],
    ]);

    $klaimLine = $klaim->lines()->first();

    // Commit agar proses anak melihat data peminjaman dan klaim
    DB::commit();

    $payloadVerifikasi = [
        'klaim_id' => $klaim->id,
        'user_id' => $admin->id,
        'catatan_admin' => null,
        'hasil' => [
            [
                'line_id' => $klaimLine->id,
                'qty_baik' => 2,
                'qty_rusak' => 0,
                'qty_hilang' => 0,
                'qty_ditolak' => 0,
            ],
        ],
    ];

    $hasil = jalankanDuaProsesKonkuren('verifikasi', $payloadVerifikasi, $payloadVerifikasi);

    $p1 = $hasil['p1'];
    $p2 = $hasil['p2'];

    $sukses = 0;
    $gagalKonflik = 0;

    foreach ([$p1, $p2] as $p) {
        if ($p['kode'] === 0 && ($p['output']['sukses'] ?? false) === true) {
            $sukses++;
        } elseif ($p['kode'] === 1 && ($p['output']['status'] ?? null) === 409) {
            $gagalKonflik++;
        }
    }

    expect($sukses)->toBe(1)
        ->and($gagalKonflik)->toBe(1);

    // Verifikasi klaim berstatus diverifikasi dan stok tersedia kembali utuh 2
    $klaimSegar = ReturnClaim::find($klaim->id);
    expect($klaimSegar->status)->toBe('diverifikasi');

    $itemSegar = Item::find($item->id);
    expect($itemSegar->tersedia)->toBe(2)
        ->and($itemSegar->total)->toBe(2);

    assertInvarian();
});
