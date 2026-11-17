<?php

use App\Services\LaporanService;
use App\Services\PeminjamanAdminService;
use App\Support\Tanggal;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SettingSeeder::class);
    $this->admin = adminUji();
    $this->data = siapkanDataLaporan($this->admin);
});

it('rekap memuat keenam status', function () {
    $data = app(LaporanService::class)->rekap([], $this->admin->name, '2026-11-17 08:30:00');

    $kode = collect($data['baris'])->pluck('kode_pinjam');

    foreach (['dipesan', 'dipinjam', 'terlambat', 'menunggu', 'selesai', 'dibatalkan'] as $kunci) {
        expect($kode)->toContain($this->data[$kunci]->kode_pinjam);
    }

    expect($data['jumlahBaris'])->toBe(6);
});

it('filter rekap sama himpunan dengan PeminjamanAdminService', function () {
    $service = app(LaporanService::class);
    $daftar = app(PeminjamanAdminService::class);

    $filterDaftar = [
        [],
        ['status' => 'dipinjam'],
        ['kategori' => $this->data['kategoriB']->id],
        ['tipe_peminjam' => 'magang'],
        ['divisi' => $this->data['divisiLain']->id],
        ['dari' => Tanggal::hariIni()],
    ];

    foreach ($filterDaftar as $filter) {
        $laporanKode = collect($service->rekap($filter, $this->admin->name, '2026-11-17 08:30:00')['baris'])
            ->pluck('kode_pinjam')->sort()->values()->all();
        $daftarKode = $daftar->daftar($filter, 1000)->pluck('kode_pinjam')->sort()->values()->all();

        expect($laporanKode)->toBe($daftarKode);
    }
});

it('belum-kembali memuat dipinjam dan terlambat, tidak memuat selesai/dibatalkan/menunggu', function () {
    $data = app(LaporanService::class)->belumKembali([], $this->admin->name, '2026-11-17 08:30:00');

    $aktifKode = collect($data['aktif'])->pluck('kode_pinjam');

    expect($aktifKode)->toContain($this->data['dipinjam']->kode_pinjam)
        ->and($aktifKode)->toContain($this->data['terlambat']->kode_pinjam)
        ->and($aktifKode)->not->toContain($this->data['selesai']->kode_pinjam)
        ->and($aktifKode)->not->toContain($this->data['dibatalkan']->kode_pinjam)
        ->and($aktifKode)->not->toContain($this->data['menunggu']->kode_pinjam);

    $pemesananKode = collect($data['pemesanan'])->pluck('kode_pinjam');
    expect($pemesananKode)->toContain($this->data['dipesan']->kode_pinjam);

    // Terlambat (hari > 0) harus di atas dipinjam (hari = 0).
    expect($data['aktif'][0]['status'])->toBe('terlambat');
});
