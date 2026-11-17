<?php

use App\Models\AuditLog;
use App\Models\Loan;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SettingSeeder::class);
    $this->admin = adminUji();
    $this->data = siapkanDataLaporan($this->admin);
});

function parseCsv(string $content, string $delimiter): array
{
    $handle = fopen('php://memory', 'r+');
    fwrite($handle, substr($content, 3));
    rewind($handle);

    $rows = [];
    while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
        if ($row === [null]) {
            continue;
        }
        $rows[] = $row;
    }
    fclose($handle);

    return $rows;
}

it('rute ekspor menolak 401 tanpa sesi', function () {
    spa()->getJson('/api/admin/exports/peminjaman.csv')->assertStatus(401);
    spa()->getJson('/api/admin/exports/stok.csv')->assertStatus(401);
});

it('ekspor peminjaman memuat BOM, header, dan pemisah bawaan titik-koma', function () {
    $res = spa()->actingAs($this->admin, 'web')->get('/api/admin/exports/peminjaman.csv');

    $res->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
        ->assertHeader('Cache-Control', 'no-store');

    $content = $res->streamedContent();
    expect(substr($content, 0, 3))->toBe("\xEF\xBB\xBF");

    $rows = parseCsv($content, ';');
    expect($rows[0])->toBe([
        'kode_pinjam', 'nama_peminjam', 'divisi', 'tipe_peminjam', 'tanggal_pinjam',
        'batas_kembali', 'status', 'hari_terlambat', 'jumlah_perpanjangan',
        'barang', 'ada_catatan',
    ]);
    expect(count($rows))->toBe(7);
});

it('pemisah koma bekerja pada ekspor peminjaman', function () {
    $res = spa()->actingAs($this->admin, 'web')->get('/api/admin/exports/peminjaman.csv?pemisah=,');

    $rows = parseCsv($res->streamedContent(), ',');
    expect($rows[0][0])->toBe('kode_pinjam');
    expect(count($rows))->toBe(7);
});

it('filter peminjaman menghasilkan himpunan yang sama dengan daftar', function () {
    $res = spa()->actingAs($this->admin, 'web')
        ->get('/api/admin/exports/peminjaman.csv?status=dipinjam');

    $rows = parseCsv($res->streamedContent(), ';');
    $kode = array_column(array_slice($rows, 1), 0);

    expect($kode)->toBe([$this->data['dipinjam']->kode_pinjam]);
});

it('neutralisasi CSV injection pada nama peminjam', function () {
    $namaJahat = ['=HYPERLINK("http://x","y")', '+1+1', '-2', '@SUM(A1)', "\tfoo"];
    foreach ($namaJahat as $nama) {
        Loan::where('id', $this->data['dipinjam']->id)->update(['nama_peminjam' => $nama]);

        $res = spa()->actingAs($this->admin, 'web')
            ->get('/api/admin/exports/peminjaman.csv?status=dipinjam');
        $rows = parseCsv($res->streamedContent(), ';');

        expect($rows[1][1])->toBe("'".$nama);
    }
});

it('nilai dengan pemisah, kutip, dan baris baru terbaca kembali', function () {
    $nama = "a;b,\"c\"\nd";
    Loan::where('id', $this->data['dipinjam']->id)->update(['nama_peminjam' => $nama]);

    $res = spa()->actingAs($this->admin, 'web')
        ->get('/api/admin/exports/peminjaman.csv?status=dipinjam');
    $rows = parseCsv($res->streamedContent(), ';');

    expect($rows[1][1])->toBe($nama);
});

it('ekspor peminjaman tidak memuat telepon', function () {
    $res = spa()->actingAs($this->admin, 'web')->get('/api/admin/exports/peminjaman.csv');

    expect($res->streamedContent())->not->toContain('081234567890');
});

it('ekspor stok memuat BOM, header, dan angka yang sama', function () {
    $res = spa()->actingAs($this->admin, 'web')->get('/api/admin/exports/stok.csv');

    $res->assertOk();
    expect(substr($res->streamedContent(), 0, 3))->toBe("\xEF\xBB\xBF");

    $rows = parseCsv($res->streamedContent(), ';');
    expect($rows[0])->toBe([
        'kategori', 'barang', 'total', 'tersedia', 'dipinjam',
        'menunggu_verifikasi', 'rusak', 'kondisi', 'boleh_diperpanjang',
    ]);
    expect(count($rows))->toBe(3);

    $api = spa()->actingAs($this->admin, 'web')->getJson('/api/admin/items')->json('data');
    $itemApi = collect($api)->firstWhere('nama', 'Laptop');

    $baris = collect($rows)->firstWhere(1, 'Laptop');
    expect((int) $baris[2])->toBe($itemApi['total']);
    expect((int) $baris[3])->toBe($itemApi['tersedia']);
    expect((int) $baris[6])->toBe($itemApi['rusak']);
});

it('filter kategori pada ekspor stok', function () {
    $res = spa()->actingAs($this->admin, 'web')
        ->get('/api/admin/exports/stok.csv?kategori='.$this->data['kategoriA']->id);

    $rows = parseCsv($res->streamedContent(), ';');
    expect(count($rows))->toBe(2);
});

it('audit ekspor tertulis', function () {
    spa()->actingAs($this->admin, 'web')->get('/api/admin/exports/peminjaman.csv')->assertOk();
    spa()->actingAs($this->admin, 'web')->get('/api/admin/exports/stok.csv')->assertOk();

    expect(AuditLog::where('aksi', 'ekspor.diunduh')->count())->toBe(2);
});
