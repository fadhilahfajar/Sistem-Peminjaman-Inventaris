<?php

use App\Models\AuditLog;
use App\Models\Loan;
use App\Models\ReturnClaim;
use App\Models\User;
use App\Support\Tanggal;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'cache.default' => 'array',
        'session.driver' => 'array',
        'sanctum.stateful' => ['localhost:5173'],
    ]);

    $this->seed(SettingSeeder::class);
});

it('setiap aksi admin, kiosk, dan artisan menulis tepat satu audit tanpa rahasia', function () {
    $rahasia = 'Sandi-Uji-12345-xyz';
    $rahasiaBaru = 'Ganti-Akhir-67890-abc';
    $telepon = '081234567890';

    $admin = User::create([
        'name' => 'Admin Audit',
        'email' => 'audit@uji.test',
        'password' => $rahasia,
        'aktif' => true,
    ]);

    $tanggal = fn (int $offset = 0): string => (new DateTimeImmutable(
        Tanggal::hariIni(),
        new DateTimeZone('Asia/Jakarta'),
    ))->modify("{$offset} days")->format('Y-m-d');

    $catat = function (string $aksi, string $entitas, ?int $userId, callable $jalankan) {
        $idSebelum = (int) AuditLog::max('id');
        [$entitasId, $hasil] = $jalankan();

        $baru = AuditLog::where('id', '>', $idSebelum)->get();
        expect($baru)->toHaveCount(1);

        $log = $baru->first();
        expect($log->aksi)->toBe($aksi)
            ->and($log->entitas)->toBe($entitas)
            ->and($log->entitas_id)->toBe($entitasId)
            ->and($log->user_id)->toBe($userId);

        return $hasil;
    };

    // Login gagal tidak boleh menulis audit kata sandi yang dicoba.
    $idSebelum = (int) AuditLog::max('id');
    spa()->postJson('/api/auth/login', [
        'email' => 'tidak-ada@uji.test',
        'password' => $rahasia,
    ])->assertStatus(422);
    expect(AuditLog::where('id', '>', $idSebelum)->count())->toBe(0);

    $catat('login', 'users', $admin->id, function () use ($admin, $rahasia) {
        spa()->postJson('/api/auth/login', [
            'email' => $admin->email,
            'password' => $rahasia,
        ])->assertOk();

        return [$admin->id, null];
    });

    $catat('ganti_password', 'users', $admin->id, function () use ($admin, $rahasia, $rahasiaBaru) {
        spa()->actingAs($admin, 'web')->postJson('/api/auth/change-password', [
            'password_lama' => $rahasia,
            'password_baru' => $rahasiaBaru,
            'password_baru_confirmation' => $rahasiaBaru,
        ])->assertOk();

        return [$admin->id, null];
    });

    $adminBaruId = $catat('akun_admin.dibuat', 'users', $admin->id, function () use ($admin, $rahasia) {
        $res = spa()->actingAs($admin, 'web')->postJson('/api/admin/users', [
            'name' => 'Admin Baru',
            'email' => 'baru-audit@uji.test',
            'password' => $rahasia,
        ])->assertCreated();

        $id = (int) $res->json('data.id');

        return [$id, $id];
    });

    $catat('akun_admin.diubah', 'users', $admin->id, function () use ($admin, $adminBaruId) {
        spa()->actingAs($admin, 'web')->patchJson("/api/admin/users/{$adminBaruId}", [
            'name' => 'Admin Baru Diubah',
        ])->assertOk();

        return [$adminBaruId, null];
    });

    $adminArtisan = $catat('akun_admin.dibuat', 'users', null, function () use ($rahasia) {
        $email = 'artisan-'.uniqid().'@uji.test';

        $this->artisan('inventaris:buat-admin', [
            'email' => $email,
            'nama' => 'Admin Artisan',
        ])
            ->expectsQuestion('Kata sandi', $rahasia)
            ->expectsQuestion('Ulangi kata sandi', $rahasia)
            ->assertSuccessful();

        $user = User::where('email', $email)->firstOrFail();

        return [$user->id, $user];
    });

    $catat('akun_admin.sandi_diganti', 'users', null, function () use ($adminArtisan, $rahasia) {
        $this->artisan('inventaris:reset-password-admin', ['email' => $adminArtisan->email])
            ->expectsQuestion('Kata sandi baru', $rahasia)
            ->expectsQuestion('Ulangi kata sandi baru', $rahasia)
            ->assertSuccessful();

        return [$adminArtisan->id, null];
    });

    $kategoriId = $catat('kategori.dibuat', 'categories', $admin->id, function () use ($admin) {
        $res = spa()->actingAs($admin, 'web')->postJson('/api/admin/categories', [
            'nama' => 'Kategori Audit',
        ])->assertCreated();

        $id = (int) $res->json('data.id');

        return [$id, $id];
    });

    $catat('kategori.diubah', 'categories', $admin->id, function () use ($admin, $kategoriId) {
        spa()->actingAs($admin, 'web')->patchJson("/api/admin/categories/{$kategoriId}", [
            'nama' => 'Kategori Audit Diubah',
        ])->assertOk();

        return [$kategoriId, null];
    });

    $divisiId = $catat('divisi.dibuat', 'divisions', $admin->id, function () use ($admin) {
        $res = spa()->actingAs($admin, 'web')->postJson('/api/admin/divisions', [
            'nama' => 'Divisi Audit',
        ])->assertCreated();

        $id = (int) $res->json('data.id');

        return [$id, $id];
    });

    $catat('divisi.diubah', 'divisions', $admin->id, function () use ($admin, $divisiId) {
        spa()->actingAs($admin, 'web')->patchJson("/api/admin/divisions/{$divisiId}", [
            'nama' => 'Divisi Audit Diubah',
        ])->assertOk();

        return [$divisiId, null];
    });

    $itemId = $catat('barang.dibuat', 'items', $admin->id, function () use ($admin, $kategoriId) {
        $res = spa()->actingAs($admin, 'web')->postJson('/api/admin/items', [
            'nama' => 'Barang Audit',
            'kategori_id' => $kategoriId,
            'jumlah_awal' => 0,
        ])->assertCreated();

        $id = (int) $res->json('data.id');

        return [$id, $id];
    });

    $catat('barang.diubah', 'items', $admin->id, function () use ($admin, $itemId, $kategoriId) {
        spa()->actingAs($admin, 'web')->putJson("/api/admin/items/{$itemId}", [
            'nama' => 'Barang Audit Diubah',
            'kategori_id' => $kategoriId,
            'boleh_diperpanjang' => true,
        ])->assertOk();

        return [$itemId, null];
    });

    $catat('tambah_stok', 'items', $admin->id, function () use ($admin, $itemId) {
        spa()->actingAs($admin, 'web')->postJson("/api/admin/items/{$itemId}/stock-adjustments", [
            'arah' => 'tambah',
            'jumlah' => 20,
            'alasan' => 'Stok awal audit',
        ])->assertOk();

        return [$itemId, null];
    });

    $catat('kurangi_stok', 'items', $admin->id, function () use ($admin, $itemId) {
        spa()->actingAs($admin, 'web')->postJson("/api/admin/items/{$itemId}/stock-adjustments", [
            'arah' => 'kurang',
            'jumlah' => 1,
            'alasan' => 'Penyesuaian audit',
        ])->assertOk();

        return [$itemId, null];
    });

    $catat('tandai_rusak', 'items', $admin->id, function () use ($admin, $itemId) {
        spa()->actingAs($admin, 'web')->postJson("/api/admin/items/{$itemId}/mark-damaged", [
            'jumlah' => 1,
            'alasan' => 'Uji perbaikan',
        ])->assertOk();

        return [$itemId, null];
    });

    $catat('selesai_perbaikan', 'items', $admin->id, function () use ($admin, $itemId) {
        spa()->actingAs($admin, 'web')->postJson("/api/admin/items/{$itemId}/repair-complete", [
            'jumlah' => 1,
            'catatan' => 'Selesai diperbaiki',
        ])->assertOk();

        return [$itemId, null];
    });

    $catat('tandai_rusak', 'items', $admin->id, function () use ($admin, $itemId) {
        spa()->actingAs($admin, 'web')->postJson("/api/admin/items/{$itemId}/mark-damaged", [
            'jumlah' => 1,
            'alasan' => 'Uji afkir',
        ])->assertOk();

        return [$itemId, null];
    });

    $catat('afkir', 'items', $admin->id, function () use ($admin, $itemId) {
        spa()->actingAs($admin, 'web')->postJson("/api/admin/items/{$itemId}/write-off", [
            'jumlah' => 1,
            'alasan' => 'Afkir audit',
        ])->assertOk();

        return [$itemId, null];
    });

    $itemHapusId = $catat('barang.dibuat', 'items', $admin->id, function () use ($admin, $kategoriId) {
        $res = spa()->actingAs($admin, 'web')->postJson('/api/admin/items', [
            'nama' => 'Barang Hapus Audit',
            'kategori_id' => $kategoriId,
        ])->assertCreated();

        $id = (int) $res->json('data.id');

        return [$id, $id];
    });

    $catat('barang.dihapus', 'items', $admin->id, function () use ($admin, $itemHapusId) {
        spa()->actingAs($admin, 'web')->deleteJson("/api/admin/items/{$itemHapusId}")->assertOk();

        return [$itemHapusId, null];
    });

    $pinjamKiosk = function (array $override = []) use ($itemId, $divisiId, $tanggal) {
        $res = spa()->postJson('/api/kiosk/loans', array_merge([
            'nama_peminjam' => 'Budi Santoso',
            'division_id' => $divisiId,
            'tipe_peminjam' => 'karyawan',
            'telepon' => '081234567890',
            'tanggal_pinjam' => $tanggal(),
            'batas_kembali' => $tanggal(3),
            'idempotency_key' => (string) Str::uuid(),
            'items' => [['item_id' => $itemId, 'qty' => 1]],
        ], $override))->assertSuccessful();

        return Loan::where('kode_pinjam', $res->json('data.kode_pinjam'))->firstOrFail();
    };

    $buatPinjam = fn (array $override = []) => $catat(
        'buat_peminjaman',
        'loans',
        null,
        function () use ($pinjamKiosk, $override) {
            $loan = $pinjamKiosk($override);

            return [$loan->id, $loan];
        },
    );

    $loanKlaim = $buatPinjam();

    $klaim = $catat('buat_klaim', 'return_claims', null, function () use ($loanKlaim, $itemId) {
        $res = spa()->postJson('/api/kiosk/return-claims', [
            'kode' => $loanKlaim->kode_pinjam,
            'idempotency_key' => (string) Str::uuid(),
            'lines' => [['item_id' => $itemId, 'kondisi' => 'baik', 'qty' => 1]],
        ])->assertSuccessful();

        $klaim = ReturnClaim::where('kode_klaim', $res->json('data.kode_klaim'))->firstOrFail();

        return [$klaim->id, $klaim];
    });

    $catat('verifikasi_klaim', 'return_claims', $admin->id, function () use ($admin, $klaim) {
        $baris = $klaim->lines()->firstOrFail();

        spa()->actingAs($admin, 'web')->postJson("/api/admin/return-claims/{$klaim->id}/verify", [
            'lines' => [[
                'line_id' => $baris->id,
                'qty_baik' => 1,
                'qty_rusak' => 0,
                'qty_hilang' => 0,
                'qty_ditolak' => 0,
            ]],
        ])->assertOk();

        return [$klaim->id, null];
    });

    $loanExtKiosk = $buatPinjam();
    $catat('perpanjang_peminjaman', 'loans', null, function () use ($loanExtKiosk) {
        spa()->postJson('/api/kiosk/extensions', [
            'kode_pinjam' => $loanExtKiosk->kode_pinjam,
            'tambahan_hari' => 1,
            'alasan' => 'Perpanjangan kiosk',
        ])->assertSuccessful();

        return [$loanExtKiosk->id, null];
    });

    $loanBatalKiosk = $buatPinjam([
        'tanggal_pinjam' => $tanggal(2),
        'batas_kembali' => $tanggal(5),
    ]);
    $catat('batalkan_peminjaman', 'loans', null, function () use ($loanBatalKiosk) {
        spa()->postJson('/api/kiosk/loans/cancel', [
            'kode_pinjam' => $loanBatalKiosk->kode_pinjam,
        ])->assertSuccessful();

        return [$loanBatalKiosk->id, null];
    });

    $loanKoreksi = $buatPinjam();
    $catat('koreksi_peminjaman', 'loans', $admin->id, function () use ($admin, $loanKoreksi, $tanggal) {
        spa()->actingAs($admin, 'web')->patchJson("/api/admin/loans/{$loanKoreksi->id}", [
            'batas_kembali' => $tanggal(5),
            'alasan' => 'Koreksi audit',
        ])->assertOk();

        return [$loanKoreksi->id, null];
    });

    $loanExtAdmin = $buatPinjam();
    $catat('perpanjang_peminjaman', 'loans', $admin->id, function () use ($admin, $loanExtAdmin) {
        spa()->actingAs($admin, 'web')->postJson("/api/admin/loans/{$loanExtAdmin->id}/extensions", [
            'tambahan_hari' => 1,
            'alasan' => 'Perpanjangan admin',
        ])->assertOk();

        return [$loanExtAdmin->id, null];
    });

    $loanBatalAdmin = $buatPinjam();
    $catat('batalkan_peminjaman', 'loans', $admin->id, function () use ($admin, $loanBatalAdmin) {
        spa()->actingAs($admin, 'web')->postJson("/api/admin/loans/{$loanBatalAdmin->id}/cancel", [
            'alasan' => 'Pembatalan admin',
        ])->assertOk();

        return [$loanBatalAdmin->id, null];
    });

    $catat('pengaturan.diubah', 'settings', $admin->id, function () use ($admin) {
        spa()->actingAs($admin, 'web')->putJson('/api/admin/settings', [
            'sesi_idle_menit' => 30,
        ])->assertOk();

        return [null, null];
    });

    $catat('laporan.diunduh', 'laporan', $admin->id, function () use ($admin) {
        spa()->actingAs($admin, 'web')->get('/api/admin/reports/rekap.pdf')->assertOk();

        return [null, null];
    });

    $catat('ekspor.diunduh', 'ekspor', $admin->id, function () use ($admin) {
        spa()->actingAs($admin, 'web')->get('/api/admin/exports/peminjaman.csv')->assertOk();

        return [null, null];
    });

    // Telusuri semua baris audit: tidak ada rahasia, kunci terlarang, atau telepon peminjam.
    $semua = AuditLog::query()->orderBy('id')->get();
    $detailSemua = $semua->map(fn (AuditLog $log): string => json_encode($log->detail))->implode("\n");

    expect($detailSemua)->not->toContain($rahasia)
        ->and($detailSemua)->not->toContain($rahasiaBaru)
        ->and($detailSemua)->not->toContain($telepon);

    foreach ($semua as $log) {
        foreach (kunciJsonRekursif($log->detail ?? []) as $kunci) {
            expect($kunci)->not->toMatch('/password|passwd|sandi|token|secret|authorization|cookie|csrf/i');
        }
    }
});

it('logout wajib menulis satu audit logout tanpa rahasia', function () {
    $user = adminUji();

    spa()->postJson('/api/auth/logout')->assertStatus(401);
    expect(AuditLog::count())->toBe(0);

    $idSebelum = (int) AuditLog::max('id');

    spa()->actingAs($user, 'web')->postJson('/api/auth/logout')
        ->assertOk()
        ->assertJsonPath('pesan', 'Berhasil keluar.');

    $baru = AuditLog::where('id', '>', $idSebelum)->get();
    expect($baru)->toHaveCount(1);

    $log = $baru->first();
    expect($log->aksi)->toBe('logout')
        ->and($log->entitas)->toBe('users')
        ->and($log->entitas_id)->toBe($user->id)
        ->and($log->user_id)->toBe($user->id)
        ->and($log->detail)->toBeNull();
});
