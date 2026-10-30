<?php

use App\Models\User;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SettingSeeder::class);
    $this->admin = User::create([
        'name' => 'Admin', 'email' => 'ganti@uji.test',
        'password' => 'sandi-lama-12345', 'aktif' => true,
    ]);
});

function kirimGanti(array $data)
{
    return test()
        ->withHeaders(['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/'])
        ->actingAs(test()->admin, 'web')
        ->postJson('/api/auth/change-password', $data);
}

it('mengganti kata sandi, menulis audit tanpa kata sandi, dan mengakhiri sesi lain', function () {
    DB::table('sessions')->insert([
        'id' => 'sesi-perangkat-lain', 'user_id' => $this->admin->id,
        'payload' => '', 'last_activity' => time(),
    ]);

    kirimGanti([
        'password_lama' => 'sandi-lama-12345',
        'password_baru' => 'sandi-baru-67890',
        'password_baru_confirmation' => 'sandi-baru-67890',
    ])->assertOk();

    expect(Hash::check('sandi-baru-67890', $this->admin->fresh()->password))->toBeTrue()
        ->and(DB::table('sessions')->where('id', 'sesi-perangkat-lain')->exists())->toBeFalse();

    $audit = DB::table('audit_logs')->where('aksi', 'ganti_password')->first();
    expect($audit)->not->toBeNull()
        ->and($audit->detail)->not->toContain('sandi');
});

it('menolak kata sandi lama yang salah dengan 422 dan bentuk galat seragam', function () {
    kirimGanti([
        'password_lama' => 'salah-semua-123',
        'password_baru' => 'sandi-baru-67890',
        'password_baru_confirmation' => 'sandi-baru-67890',
    ])->assertStatus(422)
        ->assertJsonPath('kode', 'validasi_gagal')
        ->assertJsonStructure(['kode', 'pesan', 'errors' => ['password_lama']]);

    expect(Hash::check('sandi-lama-12345', $this->admin->fresh()->password))->toBeTrue();
});

it('menolak kata sandi baru kurang dari 12 karakter', function () {
    kirimGanti([
        'password_lama' => 'sandi-lama-12345',
        'password_baru' => 'pendek', 'password_baru_confirmation' => 'pendek',
    ])->assertStatus(422)->assertJsonStructure(['errors' => ['password_baru']]);
});

it('menolak konfirmasi yang tidak sama', function () {
    kirimGanti([
        'password_lama' => 'sandi-lama-12345',
        'password_baru' => 'sandi-baru-67890', 'password_baru_confirmation' => 'beda-sekali-1234',
    ])->assertStatus(422)->assertJsonStructure(['errors' => ['password_baru']]);
});

it('menolak kata sandi baru yang sama dengan yang lama', function () {
    kirimGanti([
        'password_lama' => 'sandi-lama-12345',
        'password_baru' => 'sandi-lama-12345', 'password_baru_confirmation' => 'sandi-lama-12345',
    ])->assertStatus(422)->assertJsonStructure(['errors' => ['password_baru']]);
});

it('menolak 401 tanpa sesi', function () {
    $this->withHeaders(['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/'])
        ->postJson('/api/auth/change-password', [])
        ->assertStatus(401)->assertJsonPath('kode', 'tidak_terautentikasi');
});
