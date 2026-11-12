<?php

use App\Models\Category;
use App\Models\Division;
use App\Models\Item;
use App\Services\PeminjamanService;
use App\Support\Tanggal;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SettingSeeder::class);

    $kategori = Category::factory()->create();
    $item = Item::factory()->create([
        'nama' => 'Barang Uji '.uniqid(),
        'kategori_id' => $kategori->id,
        'total' => 10,
        'tersedia' => 10,
        'rusak' => 0,
        'boleh_diperpanjang' => true,
    ]);
    $this->item = $item;

    $divisi = Division::firstOrCreate(['nama' => 'IT'], ['aktif' => true]);

    $this->loan = app(PeminjamanService::class)->buat([
        'nama_peminjam' => 'Budi Santoso',
        'division_id' => $divisi->id,
        'tipe_peminjam' => 'karyawan',
        'telepon' => '081234567890',
        'tanggal_pinjam' => Tanggal::hariIni(),
        'batas_kembali' => (new DateTimeImmutable(Tanggal::hariIni(), new DateTimeZone('Asia/Jakarta')))->modify('+3 days')->format('Y-m-d'),
        'idempotency_key' => (string) Str::uuid(),
        'items' => [['item_id' => $item->id, 'qty' => 3]],
    ]);

    // Pemesanan masa depan untuk uji pembatalan.
    $this->loanPemesanan = app(PeminjamanService::class)->buat([
        'nama_peminjam' => 'Siti Aminah',
        'division_id' => $divisi->id,
        'tipe_peminjam' => 'magang',
        'telepon' => '081234567890',
        'tanggal_pinjam' => (new DateTimeImmutable(Tanggal::hariIni(), new DateTimeZone('Asia/Jakarta')))->modify('+2 days')->format('Y-m-d'),
        'batas_kembali' => (new DateTimeImmutable(Tanggal::hariIni(), new DateTimeZone('Asia/Jakarta')))->modify('+5 days')->format('Y-m-d'),
        'idempotency_key' => (string) Str::uuid(),
        'items' => [['item_id' => $item->id, 'qty' => 1]],
    ]);
});

it('respons kiosk tidak memuat kunci sensitif (telepon, total, rusak, tersedia, idempotency_key)', function () {
    $larangan = ['telepon', 'total', 'rusak', 'tersedia', 'idempotency_key'];

    // POST /api/kiosk/loans
    $resBuat = spa()->postJson('/api/kiosk/loans', [
        'nama_peminjam' => 'Budi Santoso',
        'division_id' => $this->loan->division_id,
        'tipe_peminjam' => 'karyawan',
        'telepon' => '081234567890',
        'tanggal_pinjam' => Tanggal::hariIni(),
        'batas_kembali' => (new DateTimeImmutable(Tanggal::hariIni(), new DateTimeZone('Asia/Jakarta')))->modify('+3 days')->format('Y-m-d'),
        'idempotency_key' => (string) Str::uuid(),
        'items' => [['item_id' => $this->item->id, 'qty' => 1]],
    ])->assertSuccessful();
    expect(array_intersect(kunciJsonRekursif($resBuat->json()), $larangan))->toBeEmpty();

    // POST /api/kiosk/loans/lookup
    $resLookup = spa()->postJson('/api/kiosk/loans/lookup', ['kode' => $this->loan->kode_pinjam])->assertOk();
    expect(array_intersect(kunciJsonRekursif($resLookup->json()), $larangan))->toBeEmpty();

    // POST /api/kiosk/return-claims
    $resKlaim = spa()->postJson('/api/kiosk/return-claims', [
        'kode' => $this->loan->kode_pinjam,
        'idempotency_key' => (string) Str::uuid(),
        'lines' => [['item_id' => $this->item->id, 'kondisi' => 'baik', 'qty' => 1]],
    ])->assertSuccessful();
    expect(array_intersect(kunciJsonRekursif($resKlaim->json()), $larangan))->toBeEmpty();

    // POST /api/kiosk/extensions
    $resExt = spa()->postJson('/api/kiosk/extensions', [
        'kode_pinjam' => $this->loan->kode_pinjam,
        'tambahan_hari' => 1,
        'alasan' => 'Butuh lagi',
    ])->assertSuccessful();
    expect(array_intersect(kunciJsonRekursif($resExt->json()), $larangan))->toBeEmpty();

    // POST /api/kiosk/loans/cancel
    $resBatal = spa()->postJson('/api/kiosk/loans/cancel', [
        'kode_pinjam' => $this->loanPemesanan->kode_pinjam,
    ])->assertSuccessful();
    expect(array_intersect(kunciJsonRekursif($resBatal->json()), $larangan))->toBeEmpty();
});

it('katalog kiosk boleh memuat tersedia tetapi tidak total dan rusak', function () {
    $res = spa()->getJson('/api/kiosk/items')->assertOk();
    $kunci = kunciJsonRekursif($res->json());

    expect($kunci)->toContain('tersedia')
        ->and(array_intersect($kunci, ['total', 'rusak']))->toBeEmpty();
});
