<?php

use App\Exceptions\GalatBisnis;
use App\Models\Category;
use App\Models\Division;
use App\Models\Item;
use App\Models\Loan;
use App\Models\LoanExtension;
use App\Models\Setting;
use App\Services\KartuPeminjamanService;
use App\Services\KlaimService;
use App\Services\PembatalanService;
use App\Services\PeminjamanService;
use App\Services\PerpanjanganService;
use App\Services\StatusPeminjaman;
use App\Services\VerifikasiService;
use App\Support\Tanggal;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SettingSeeder::class);
    RateLimiter::clear('kiosk-tulis:127.0.0.1');
    RateLimiter::clear('kiosk-tulis:192.168.1.101');
    RateLimiter::clear('kiosk-tulis:192.168.1.102');
    Cache::flush();
});

function lookupTglRel(int $hari, ?string $dari = null): string
{
    $base = $dari ?? Tanggal::hariIni();

    return (new DateTimeImmutable($base, new DateTimeZone('Asia/Jakarta')))
        ->modify("{$hari} days")
        ->format('Y-m-d');
}

function lookupBuatPeminjaman(array $override = []): Loan
{
    $kategori = Category::factory()->create();
    $item = Item::factory()->create([
        'nama' => 'Barang Uji '.uniqid(),
        'kategori_id' => $kategori->id,
        'total' => 10,
        'tersedia' => 10,
        'rusak' => 0,
        'boleh_diperpanjang' => $override['boleh_diperpanjang'] ?? true,
    ]);
    $divisi = Division::firstOrCreate(['nama' => 'IT'], ['aktif' => true]);
    $hariIni = Tanggal::hariIni();

    $tanggalPinjam = $override['tanggal_pinjam'] ?? $hariIni;
    $batasKembali = $override['batas_kembali'] ?? lookupTglRel(3, $tanggalPinjam);

    $payload = [
        'nama_peminjam' => $override['nama_peminjam'] ?? 'Budi Santoso',
        'division_id' => $divisi->id,
        'tipe_peminjam' => $override['tipe_peminjam'] ?? 'karyawan',
        'telepon' => $override['telepon'] ?? '081234567890',
        'tanggal_pinjam' => $tanggalPinjam,
        'batas_kembali' => $batasKembali,
        'idempotency_key' => (string) Str::uuid(),
        'items' => [
            [
                'item_id' => $override['item_id'] ?? $item->id,
                'qty' => $override['qty'] ?? 3,
            ],
        ],
    ];

    $loan = app(PeminjamanService::class)->buat($payload);

    if (isset($override['backdate_tanggal_pinjam']) || isset($override['backdate_batas_kembali'])) {
        $loan->update([
            'tanggal_pinjam' => $override['backdate_tanggal_pinjam'] ?? $loan->tanggal_pinjam,
            'batas_kembali' => $override['backdate_batas_kembali'] ?? $loan->batas_kembali,
        ]);
        $loan->refresh();
    }

    return $loan;
}

function lookupPeriksaRekursifBebasKataTerlarang(mixed $data): void
{
    $terlarang = ['telepon', 'tersedia', 'total', 'rusak'];

    if (is_array($data)) {
        foreach ($data as $key => $val) {
            foreach ($terlarang as $kata) {
                expect(strtolower((string) $key))->not->toContain($kata);
            }
            lookupPeriksaRekursifBebasKataTerlarang($val);
        }
    } elseif (is_string($data)) {
        foreach ($terlarang as $kata) {
            expect(strtolower($data))->not->toContain($kata);
        }
    }
}

// --------------------------------------------------------------------------
// BAGIAN 1: PEMBATAS PERCOBAAN KODE (K-6, T-06)
// --------------------------------------------------------------------------

it('lima kegagalan berturut-turut lalu permintaan keenam dengan kode BENAR tetap 429 (T-06) dengan pesan memuat N menit dan header Retry-After', function () {
    $loan = lookupBuatPeminjaman();

    // 5 kali percobaan gagal berturut-turut dengan kode yang tidak ditemukan
    for ($i = 1; $i <= 5; $i++) {
        $res = spa()->postJson('/api/kiosk/loans/lookup', ['kode' => 'PJM-999999']);
        $res->assertStatus(404)
            ->assertJson([
                'kode' => 'kode_tidak_ditemukan',
                'pesan' => 'Kode peminjaman tidak ditemukan.',
            ]);
    }

    // Permintaan ke-6 dengan kode BENAR tetap ditolak 429
    $resKeenam = spa()->postJson('/api/kiosk/loans/lookup', ['kode' => $loan->kode_pinjam]);

    $resKeenam->assertStatus(429)
        ->assertJson([
            'kode' => 'lookup_terkunci',
        ])
        ->assertHeader('Retry-After');

    expect($resKeenam->json('pesan'))->toMatch('/Terlalu banyak percobaan, coba lagi dalam \d+ menit\./')
        ->and($resKeenam->json('pesan'))->toContain('5 menit');
});

it('setelah travel melewati masa kunci, kode benar berhasil', function () {
    $loan = lookupBuatPeminjaman();

    for ($i = 1; $i <= 5; $i++) {
        spa()->postJson('/api/kiosk/loans/lookup', ['kode' => 'PJM-999999'])->assertStatus(404);
    }

    // Pastikan terkunci
    spa()->postJson('/api/kiosk/loans/lookup', ['kode' => $loan->kode_pinjam])->assertStatus(429);

    // Travel 6 menit ke depan melewati masa kunci 5 menit
    $this->travel(6)->minutes();

    // Reset rate limiter IP bawaan (kiosk-tulis) agar tidak menghalangi
    RateLimiter::clear('kiosk-tulis:127.0.0.1');

    $res = spa()->postJson('/api/kiosk/loans/lookup', ['kode' => $loan->kode_pinjam]);

    $res->assertOk()
        ->assertJsonPath('data.kode_pinjam', $loan->kode_pinjam);
});

it('4 gagal, 1 sukses, 4 gagal tidak terkunci', function () {
    $loan = lookupBuatPeminjaman();

    // 4 kali gagal
    for ($i = 1; $i <= 4; $i++) {
        spa()->postJson('/api/kiosk/loans/lookup', ['kode' => 'PJM-999999'])->assertStatus(404);
    }

    // 1 kali sukses mereset penghitung
    RateLimiter::clear('kiosk-tulis:127.0.0.1');
    spa()->postJson('/api/kiosk/loans/lookup', ['kode' => $loan->kode_pinjam])->assertOk();

    // 4 kali gagal lagi
    for ($i = 1; $i <= 4; $i++) {
        spa()->postJson('/api/kiosk/loans/lookup', ['kode' => 'PJM-999999'])->assertStatus(404);
    }

    // Permintaan berikutnya dengan kode benar tetap berhasil (tidak terkunci)
    RateLimiter::clear('kiosk-tulis:127.0.0.1');
    spa()->postJson('/api/kiosk/loans/lookup', ['kode' => $loan->kode_pinjam])->assertOk();
});

it('kedua jenis kegagalan (format salah dan tidak ditemukan) menghasilkan respons identik dan sama-sama menaikkan penghitung', function () {
    $loan = lookupBuatPeminjaman();

    // Format salah (huruf I terlarang di BR-17)
    $resFormatSalah = spa()->postJson('/api/kiosk/loans/lookup', ['kode' => 'PJM-IIIIII']);

    // Tidak ditemukan (format valid)
    $resTidakDitemukan = spa()->postJson('/api/kiosk/loans/lookup', ['kode' => 'PJM-999999']);

    expect($resFormatSalah->status())->toBe(404)
        ->and($resTidakDitemukan->status())->toBe(404)
        ->and($resFormatSalah->json())->toBe($resTidakDitemukan->json())
        ->and($resFormatSalah->json())->toBe([
            'kode' => 'kode_tidak_ditemukan',
            'pesan' => 'Kode peminjaman tidak ditemukan.',
        ]);

    // Tambah 3 kegagalan lagi (total 5: 1 format salah, 4 tidak ditemukan)
    for ($i = 1; $i <= 3; $i++) {
        spa()->postJson('/api/kiosk/loans/lookup', ['kode' => 'PJM-888888'])->assertStatus(404);
    }

    // Ke-6 terkunci
    spa()->postJson('/api/kiosk/loans/lookup', ['kode' => $loan->kode_pinjam])->assertStatus(429);
});

it('perubahan kiosk_lookup_maks_gagal ke 2 lewat settings langsung berlaku', function () {
    $loan = lookupBuatPeminjaman();

    Setting::where('kunci', 'kiosk_lookup_maks_gagal')->update(['nilai' => '2']);

    // 2 kali gagal langsung mencapai batas baru
    spa()->postJson('/api/kiosk/loans/lookup', ['kode' => 'PJM-999999'])->assertStatus(404);
    spa()->postJson('/api/kiosk/loans/lookup', ['kode' => 'PJM-999999'])->assertStatus(404);

    // Ke-3 sudah terkunci 429
    spa()->postJson('/api/kiosk/loans/lookup', ['kode' => $loan->kode_pinjam])->assertStatus(429);
});

it('IP berbeda tidak ikut terkunci', function () {
    $loan = lookupBuatPeminjaman();

    // IP 192.168.1.101 gagal 5 kali
    for ($i = 1; $i <= 5; $i++) {
        $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.101'])
            ->postJson('/api/kiosk/loans/lookup', ['kode' => 'PJM-999999'])
            ->assertStatus(404);
    }

    // IP 192.168.1.101 terkunci
    $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.101'])
        ->postJson('/api/kiosk/loans/lookup', ['kode' => $loan->kode_pinjam])
        ->assertStatus(429);

    // IP 192.168.1.102 TIDAK terkunci dan berhasil lookup
    $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.102'])
        ->postJson('/api/kiosk/loans/lookup', ['kode' => $loan->kode_pinjam])
        ->assertOk()
        ->assertJsonPath('data.kode_pinjam', $loan->kode_pinjam);
});

it('menolak kode kosong atau bukan string dengan 422', function () {
    spa()->postJson('/api/kiosk/loans/lookup', [])->assertStatus(422);
    spa()->postJson('/api/kiosk/loans/lookup', ['kode' => ''])->assertStatus(422);
    spa()->postJson('/api/kiosk/loans/lookup', ['kode' => 12345])->assertStatus(422);
});

// --------------------------------------------------------------------------
// BAGIAN 2: KARTU PEMINJAMAN (FR-K-04, FR-K-09, DESAIN 4, 10.2)
// --------------------------------------------------------------------------

it('peminjaman hari ini memiliki status dipinjam dan hitung_mundur sisa_hari benar', function () {
    $hariIni = Tanggal::hariIni();
    $loan = lookupBuatPeminjaman([
        'tanggal_pinjam' => $hariIni,
        'batas_kembali' => lookupTglRel(3, $hariIni),
    ]);

    $res = spa()->postJson('/api/kiosk/loans/lookup', ['kode' => $loan->kode_pinjam]);

    $res->assertOk()
        ->assertJsonPath('data.status', 'dipinjam')
        ->assertJsonPath('data.pemesanan', false)
        ->assertJsonPath('data.hitung_mundur.sisa_hari', 3)
        ->assertJsonPath('data.hitung_mundur.hari_terlambat', 0)
        ->assertJsonPath('data.aksi.klaim.boleh', true)
        ->assertJsonPath('data.aksi.perpanjang.boleh', true)
        ->assertJsonPath('data.aksi.batal.boleh', false);
});

it('pemesanan memiliki pemesanan true, hitung_mundur null, klaim.boleh false, batal.boleh true, perpanjang.boleh false', function () {
    $besok = lookupTglRel(1);
    $loan = lookupBuatPeminjaman([
        'tanggal_pinjam' => $besok,
        'batas_kembali' => lookupTglRel(4, $besok),
    ]);

    $res = spa()->postJson('/api/kiosk/loans/lookup', ['kode' => $loan->kode_pinjam]);

    $res->assertOk()
        ->assertJsonPath('data.status', 'dipesan')
        ->assertJsonPath('data.pemesanan', true)
        ->assertJsonPath('data.hitung_mundur.sisa_hari', null)
        ->assertJsonPath('data.hitung_mundur.hari_terlambat', null)
        ->assertJsonPath('data.aksi.klaim.boleh', false)
        ->assertJsonPath('data.aksi.klaim.alasan', 'Pemesanan belum dimulai, hanya dapat dibatalkan.')
        ->assertJsonPath('data.aksi.batal.boleh', true)
        ->assertJsonPath('data.aksi.batal.alasan', null)
        ->assertJsonPath('data.aksi.perpanjang.boleh', false)
        ->assertJsonPath('data.aksi.perpanjang.alasan', 'Pemesanan tidak dapat diperpanjang.');
});

it('kartu terlambat memiliki hari_terlambat benar dan aksi.perpanjang.boleh false dengan alasan', function () {
    $hariIni = Tanggal::hariIni();
    $loan = lookupBuatPeminjaman([
        'backdate_tanggal_pinjam' => lookupTglRel(-5, $hariIni),
        'backdate_batas_kembali' => lookupTglRel(-2, $hariIni),
    ]);

    $res = spa()->postJson('/api/kiosk/loans/lookup', ['kode' => $loan->kode_pinjam]);

    $res->assertOk()
        ->assertJsonPath('data.status', 'terlambat')
        ->assertJsonPath('data.hitung_mundur.hari_terlambat', 2)
        ->assertJsonPath('data.hitung_mundur.sisa_hari', 0)
        ->assertJsonPath('data.aksi.perpanjang.boleh', false)
        ->assertJsonPath('data.aksi.perpanjang.alasan', 'Sudah terlambat, hubungi petugas IT.');
});

it('setelah klaim terverifikasi riwayat memuat hasil dan catatan_admin sedangkan klaim menunggu tidak memuat catatan_admin', function () {
    $loan = lookupBuatPeminjaman(['qty' => 4]);
    $item = $loan->items->first()->item;

    // Klaim 1: 2 unit
    $klaim1 = app(KlaimService::class)->buat([
        'kode_pinjam' => $loan->kode_pinjam,
        'idempotency_key' => (string) Str::uuid(),
        'catatan_peminjam' => 'Klaim pertama 2 unit',
        'baris' => [
            [
                'item_id' => $item->id,
                'baik' => 1,
                'rusak' => 1,
                'hilang' => 0,
            ],
        ],
    ]);

    // Verifikasi klaim 1
    $admin = adminUji();
    $linesPayload = $klaim1->lines->map(fn ($line) => [
        'line_id' => $line->id,
        'qty_baik' => $line->kondisi_dilaporkan === 'baik' ? (int) $line->qty : 0,
        'qty_rusak' => $line->kondisi_dilaporkan === 'rusak' ? (int) $line->qty : 0,
        'qty_hilang' => 0,
        'qty_ditolak' => 0,
    ])->values()->all();

    app(VerifikasiService::class)->proses(
        $klaim1->id,
        $linesPayload,
        'Verifikasi klaim 1 beres',
        $admin->id
    );

    // Klaim 2: 1 unit, dibiarkan berstatus menunggu
    $klaim2 = app(KlaimService::class)->buat([
        'kode_pinjam' => $loan->kode_pinjam,
        'idempotency_key' => (string) Str::uuid(),
        'catatan_peminjam' => 'Klaim kedua 1 unit',
        'baris' => [
            [
                'item_id' => $item->id,
                'baik' => 1,
                'rusak' => 0,
                'hilang' => 0,
            ],
        ],
    ]);

    $res = spa()->postJson('/api/kiosk/loans/lookup', ['kode' => $loan->kode_pinjam]);
    $res->assertOk();

    $riwayat = $res->json('data.klaim');
    expect($riwayat)->toHaveCount(2);

    // Urutan terbaru dulu
    $klaimMenungguJson = collect($riwayat)->firstWhere('kode_klaim', $klaim2->kode_klaim);
    $klaimVerifikasiJson = collect($riwayat)->firstWhere('kode_klaim', $klaim1->kode_klaim);

    // Klaim menunggu TIDAK memuat catatan_admin dan TIDAK memuat hasil
    expect($klaimMenungguJson)->not->toHaveKey('catatan_admin')
        ->and($klaimMenungguJson)->not->toHaveKey('hasil')
        ->and($klaimMenungguJson['status'])->toBe('menunggu')
        ->and($klaimMenungguJson['catatan_peminjam'])->toBe('Klaim kedua 1 unit');

    // Klaim terverifikasi MEMUAT catatan_admin dan baris hasil
    expect($klaimVerifikasiJson)->toHaveKey('catatan_admin')
        ->and($klaimVerifikasiJson['catatan_admin'])->toBe('Verifikasi klaim 1 beres')
        ->and($klaimVerifikasiJson)->toHaveKey('hasil')
        ->and($klaimVerifikasiJson['hasil'])->toHaveCount(2)
        ->and($klaimVerifikasiJson['hasil'][0])->toHaveKeys([
            'nama', 'kondisi_dilaporkan', 'qty', 'qty_baik', 'qty_rusak', 'qty_hilang', 'qty_ditolak',
        ]);

    assertInvarian(false);
});

it('respons tidak memuat telepon dan tidak memuat kata tersedia, total, atau rusak di mana pun pada data normal', function () {
    $loan = lookupBuatPeminjaman();

    $res = spa()->postJson('/api/kiosk/loans/lookup', ['kode' => $loan->kode_pinjam]);
    $res->assertOk();

    $json = $res->json();

    // Pastikan tidak ada telepon
    expect($json)->not->toHaveKey('telepon')
        ->and($json['data'])->not->toHaveKey('telepon')
        ->and($json['data'])->not->toHaveKey('id')
        ->and($json['data'])->not->toHaveKey('idempotency_key');

    // Periksa rekursif seluruh badan JSON
    lookupPeriksaRekursifBebasKataTerlarang($json);
});

it('sisa per barang benar pada pengembalian bertahap dan unit ditolak kembali menjadi sisa', function () {
    // Pinjam 3 unit
    $loan = lookupBuatPeminjaman(['qty' => 3]);
    $item = $loan->items->first()->item;

    // Klaim 2 unit
    $klaim = app(KlaimService::class)->buat([
        'kode_pinjam' => $loan->kode_pinjam,
        'idempotency_key' => (string) Str::uuid(),
        'catatan_peminjam' => 'Kembalikan 2 unit',
        'baris' => [
            [
                'item_id' => $item->id,
                'baik' => 2,
                'rusak' => 0,
                'hilang' => 0,
            ],
        ],
    ]);

    // Verifikasi: 1 unit baik, 1 unit ditolak
    $admin = adminUji();
    app(VerifikasiService::class)->proses(
        $klaim->id,
        [
            [
                'line_id' => $klaim->lines->first()->id,
                'qty_baik' => 1,
                'qty_rusak' => 0,
                'qty_hilang' => 0,
                'qty_ditolak' => 1,
            ],
        ],
        '1 unit ditolak karena fisik belum diserahkan',
        $admin->id
    );

    $res = spa()->postJson('/api/kiosk/loans/lookup', ['kode' => $loan->kode_pinjam]);
    $res->assertOk();

    $itemData = $res->json('data.items.0');

    expect($itemData['qty_dipinjam'])->toBe(3)
        ->and($itemData['terverifikasi'])->toBe(1)
        ->and($itemData['menunggu'])->toBe(0)
        ->and($itemData['sisa'])->toBe(2); // 1 belum diklaim + 1 ditolak

    assertInvarian(false);
});

// --------------------------------------------------------------------------
// BAGIAN 3: KONSISTENSI AKSI DENGAN SERVICE DOMAIN
// --------------------------------------------------------------------------

it('konsistensi aksi perpanjang dan batal pada keadaan normal', function () {
    $loan = lookupBuatPeminjaman();

    $res = spa()->postJson('/api/kiosk/loans/lookup', ['kode' => $loan->kode_pinjam]);
    $data = $res->json('data');

    // Aksi perpanjang boleh: PerpanjanganService::olehPeminjam harus berhasil
    expect($data['aksi']['perpanjang']['boleh'])->toBeTrue();
    $perpanjangService = app(PerpanjanganService::class);
    $hasil = $perpanjangService->olehPeminjam($loan->kode_pinjam, 1);
    expect($hasil)->toBeInstanceOf(Loan::class);

    // Aksi batal tidak boleh: PembatalanService::olehPeminjam harus melempar GalatBisnis
    expect($data['aksi']['batal']['boleh'])->toBeFalse();
    $pembatalanService = app(PembatalanService::class);
    expect(fn () => $pembatalanService->olehPeminjam($loan->kode_pinjam))
        ->toThrow(GalatBisnis::class);

    // Konsistensi KartuPeminjamanService dengan StatusPeminjaman
    $statusInfo = app(StatusPeminjaman::class)->dariModel($loan, Tanggal::hariIni());
    $totalSisa = collect($data['items'])->sum('sisa');
    $totalMenunggu = collect($data['items'])->sum('menunggu');
    expect($totalSisa)->toBe($statusInfo['o'])
        ->and($totalMenunggu)->toBe($statusInfo['p']);
});

it('konsistensi aksi perpanjang dan batal pada keadaan terlambat', function () {
    $hariIni = Tanggal::hariIni();
    $loan = lookupBuatPeminjaman([
        'backdate_tanggal_pinjam' => lookupTglRel(-5, $hariIni),
        'backdate_batas_kembali' => lookupTglRel(-2, $hariIni),
    ]);

    $res = spa()->postJson('/api/kiosk/loans/lookup', ['kode' => $loan->kode_pinjam]);
    $data = $res->json('data');

    expect($data['aksi']['perpanjang']['boleh'])->toBeFalse();
    expect(fn () => app(PerpanjanganService::class)->olehPeminjam($loan->kode_pinjam, 1))
        ->toThrow(GalatBisnis::class);

    expect($data['aksi']['batal']['boleh'])->toBeFalse();
    expect(fn () => app(PembatalanService::class)->olehPeminjam($loan->kode_pinjam))
        ->toThrow(GalatBisnis::class);

    $statusInfo = app(StatusPeminjaman::class)->dariModel($loan, Tanggal::hariIni());
    expect(collect($data['items'])->sum('sisa'))->toBe($statusInfo['o'])
        ->and(collect($data['items'])->sum('menunggu'))->toBe($statusInfo['p']);
});

it('konsistensi aksi perpanjang dan batal pada kuota perpanjangan habis', function () {
    $loan = lookupBuatPeminjaman();

    // Buat 2 perpanjangan manual sesuai kuota maks_perpanjangan bawaan (2)
    LoanExtension::create([
        'loan_id' => $loan->id,
        'batas_lama' => $loan->batas_kembali,
        'batas_baru' => lookupTglRel(2, $loan->batas_kembali->format('Y-m-d')),
        'tambahan_hari' => 2,
        'diajukan_oleh' => 'peminjam',
    ]);
    LoanExtension::create([
        'loan_id' => $loan->id,
        'batas_lama' => lookupTglRel(2, $loan->batas_kembali->format('Y-m-d')),
        'batas_baru' => lookupTglRel(4, $loan->batas_kembali->format('Y-m-d')),
        'tambahan_hari' => 2,
        'diajukan_oleh' => 'peminjam',
    ]);

    $res = spa()->postJson('/api/kiosk/loans/lookup', ['kode' => $loan->kode_pinjam]);
    $data = $res->json('data');

    expect($data['aksi']['perpanjang']['boleh'])->toBeFalse()
        ->and($data['aksi']['perpanjang']['alasan'])->toBe('Kuota perpanjangan habis, hubungi petugas IT.');
    expect(fn () => app(PerpanjanganService::class)->olehPeminjam($loan->kode_pinjam, 1))
        ->toThrow(GalatBisnis::class);

    expect($data['aksi']['batal']['boleh'])->toBeFalse();
    expect(fn () => app(PembatalanService::class)->olehPeminjam($loan->kode_pinjam))
        ->toThrow(GalatBisnis::class);

    $statusInfo = app(StatusPeminjaman::class)->dariModel($loan, Tanggal::hariIni());
    expect(collect($data['items'])->sum('sisa'))->toBe($statusInfo['o'])
        ->and(collect($data['items'])->sum('menunggu'))->toBe($statusInfo['p']);
});

it('konsistensi aksi perpanjang dan batal bila barang tidak boleh diperpanjang', function () {
    $loan = lookupBuatPeminjaman(['boleh_diperpanjang' => false]);

    $res = spa()->postJson('/api/kiosk/loans/lookup', ['kode' => $loan->kode_pinjam]);
    $data = $res->json('data');

    expect($data['aksi']['perpanjang']['boleh'])->toBeFalse()
        ->and($data['aksi']['perpanjang']['alasan'])->toContain('tidak boleh diperpanjang');
    expect(fn () => app(PerpanjanganService::class)->olehPeminjam($loan->kode_pinjam, 1))
        ->toThrow(GalatBisnis::class);

    expect($data['aksi']['batal']['boleh'])->toBeFalse();
    expect(fn () => app(PembatalanService::class)->olehPeminjam($loan->kode_pinjam))
        ->toThrow(GalatBisnis::class);

    $statusInfo = app(StatusPeminjaman::class)->dariModel($loan, Tanggal::hariIni());
    expect(collect($data['items'])->sum('sisa'))->toBe($statusInfo['o'])
        ->and(collect($data['items'])->sum('menunggu'))->toBe($statusInfo['p']);
});

it('konsistensi aksi perpanjang dan batal pada pemesanan ke depan', function () {
    $besok = lookupTglRel(1);
    $loan = lookupBuatPeminjaman([
        'tanggal_pinjam' => $besok,
        'batas_kembali' => lookupTglRel(4, $besok),
    ]);

    $res = spa()->postJson('/api/kiosk/loans/lookup', ['kode' => $loan->kode_pinjam]);
    $data = $res->json('data');

    expect($data['aksi']['perpanjang']['boleh'])->toBeFalse();
    expect(fn () => app(PerpanjanganService::class)->olehPeminjam($loan->kode_pinjam, 1))
        ->toThrow(GalatBisnis::class);

    expect($data['aksi']['batal']['boleh'])->toBeTrue();
    $hasilBatal = app(PembatalanService::class)->olehPeminjam($loan->kode_pinjam);
    expect($hasilBatal->dibatalkan_pada)->not->toBeNull();

    $statusInfo = app(StatusPeminjaman::class)->dariModel($loan, Tanggal::hariIni());
    expect(collect($data['items'])->sum('sisa'))->toBe($statusInfo['o'])
        ->and(collect($data['items'])->sum('menunggu'))->toBe($statusInfo['p']);

    assertInvarian(false);
});

it('konsistensi aksi perpanjang dan batal bila semua unit sudah diklaim', function () {
    $loan = lookupBuatPeminjaman(['qty' => 2]);
    $item = $loan->items->first()->item;

    // Klaim seluruh unit (2)
    app(KlaimService::class)->buat([
        'kode_pinjam' => $loan->kode_pinjam,
        'idempotency_key' => (string) Str::uuid(),
        'catatan_peminjam' => 'Klaim semua',
        'baris' => [
            [
                'item_id' => $item->id,
                'baik' => 2,
                'rusak' => 0,
                'hilang' => 0,
            ],
        ],
    ]);

    $res = spa()->postJson('/api/kiosk/loans/lookup', ['kode' => $loan->kode_pinjam]);
    $data = $res->json('data');

    expect($data['aksi']['perpanjang']['boleh'])->toBeFalse()
        ->and($data['aksi']['perpanjang']['alasan'])->toBe('Semua unit sudah diklaim.');
    expect(fn () => app(PerpanjanganService::class)->olehPeminjam($loan->kode_pinjam, 1))
        ->toThrow(GalatBisnis::class);

    expect($data['aksi']['batal']['boleh'])->toBeFalse();
    expect(fn () => app(PembatalanService::class)->olehPeminjam($loan->kode_pinjam))
        ->toThrow(GalatBisnis::class);

    $statusInfo = app(StatusPeminjaman::class)->dariModel($loan, Tanggal::hariIni());
    expect(collect($data['items'])->sum('sisa'))->toBe($statusInfo['o'])
        ->and(collect($data['items'])->sum('menunggu'))->toBe($statusInfo['p']);
});

it('konsistensi aksi perpanjang dan batal bila peminjaman sudah dibatalkan', function () {
    $besok = lookupTglRel(1);
    $loan = lookupBuatPeminjaman([
        'tanggal_pinjam' => $besok,
        'batas_kembali' => lookupTglRel(4, $besok),
    ]);

    app(PembatalanService::class)->olehPeminjam($loan->kode_pinjam);

    $res = spa()->postJson('/api/kiosk/loans/lookup', ['kode' => $loan->kode_pinjam]);
    $data = $res->json('data');

    expect($data['aksi']['perpanjang']['boleh'])->toBeFalse();
    expect(fn () => app(PerpanjanganService::class)->olehPeminjam($loan->kode_pinjam, 1))
        ->toThrow(GalatBisnis::class);

    expect($data['aksi']['batal']['boleh'])->toBeFalse();
    expect(fn () => app(PembatalanService::class)->olehPeminjam($loan->kode_pinjam))
        ->toThrow(GalatBisnis::class);

    $statusInfo = app(StatusPeminjaman::class)->dariModel($loan, Tanggal::hariIni());
    expect(collect($data['items'])->sum('sisa'))->toBe($statusInfo['o'])
        ->and(collect($data['items'])->sum('menunggu'))->toBe($statusInfo['p']);

    assertInvarian(false);
});
