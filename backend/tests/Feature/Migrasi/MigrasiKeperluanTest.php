<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->migrasi = require database_path('migrations/2026_11_20_000000_tambah_keperluan_pada_loans_table.php');
    $this->migrasi->up();
    $this->divisiId = null;
    $this->loanId = null;

    $this->sisipkanLama = function (?string $keperluan = null): int {
        $this->divisiId ??= DB::table('divisions')->insertGetId([
            'nama' => 'Divisi Lama '.Str::uuid(),
            'aktif' => 1,
        ]);

        $baris = [
            'kode_pinjam' => 'PJM-'.strtoupper(Str::random(6)),
            'nama_peminjam' => 'Peminjam Lama',
            'division_id' => $this->divisiId,
            'tipe_peminjam' => 'karyawan',
            'telepon' => '081234567890',
            'tanggal_pinjam' => '2026-11-20',
            'batas_kembali_awal' => '2026-11-23',
            'batas_kembali' => '2026-11-23',
            'idempotency_key' => (string) Str::uuid(),
        ];

        if (Schema::hasColumn('loans', 'keperluan')) {
            $baris['keperluan'] = $keperluan;
        }

        $this->loanId = DB::table('loans')->insertGetId($baris);

        return $this->loanId;
    };
});

afterEach(function () {
    if ($this->loanId !== null) {
        DB::table('loans')->where('id', $this->loanId)->delete();
    }

    if ($this->divisiId !== null) {
        DB::table('divisions')->where('id', $this->divisiId)->delete();
    }

    $this->migrasi->up();
});

it('down lalu up mempertahankan baris lama dengan keperluan NULL dan kolom VARCHAR(255) nullable', function () {
    $this->migrasi->down();
    expect(Schema::hasColumn('loans', 'keperluan'))->toBeFalse();

    $id = ($this->sisipkanLama)();
    $this->migrasi->up();

    expect(DB::table('loans')->where('id', $id)->value('keperluan'))->toBeNull();

    $kolom = DB::selectOne(
        "SELECT COLUMN_TYPE, IS_NULLABLE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'loans' AND COLUMN_NAME = 'keperluan'"
    );

    expect(strtolower($kolom->COLUMN_TYPE))->toBe('varchar(255)')
        ->and(strtoupper($kolom->IS_NULLABLE))->toBe('YES');
});

it('up dua kali tidak error', function () {
    $this->migrasi->up();
    $this->migrasi->up();

    expect(Schema::hasColumn('loans', 'keperluan'))->toBeTrue();
});

it('down lalu up mempertahankan baris dan data lainnya', function () {
    $id = ($this->sisipkanLama)('Presentasi rapat');

    $this->migrasi->down();
    expect(Schema::hasColumn('loans', 'keperluan'))->toBeFalse();

    $this->migrasi->up();

    $baris = DB::table('loans')->where('id', $id)->first();
    expect($baris)->not->toBeNull()
        ->and($baris->nama_peminjam)->toBe('Peminjam Lama')
        ->and($baris->telepon)->toBe('081234567890')
        ->and($baris->keperluan)->toBeNull();
});
