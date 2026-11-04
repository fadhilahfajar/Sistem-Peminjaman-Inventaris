<?php

use App\Models\Category;
use App\Models\Division;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('hanya menampilkan kategori aktif dengan field id dan nama, terurut nama', function () {
    Category::factory()->create(['nama' => 'Zebra Kategori', 'aktif' => true]);
    Category::factory()->create(['nama' => 'Audio Video', 'aktif' => true]);
    Category::factory()->nonaktif()->create(['nama' => 'Kategori Nonaktif']);

    $response = $this->getJson('/api/kiosk/categories');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'nama'],
            ],
        ]);

    $data = $response->json('data');
    expect($data)->toHaveCount(2);

    $item = $data[0];
    expect(array_keys($item))->toEqualCanonicalizing(['id', 'nama'])
        ->and($item)->not->toHaveKey('aktif');

    $namaList = collect($data)->pluck('nama')->all();
    expect($namaList)->toBe(['Audio Video', 'Zebra Kategori'])
        ->and($namaList)->not->toContain('Kategori Nonaktif');
});

it('hanya menampilkan divisi aktif dengan field id dan nama, terurut nama', function () {
    Division::factory()->create(['nama' => 'Umum', 'aktif' => true]);
    Division::factory()->create(['nama' => 'Akademik', 'aktif' => true]);
    Division::factory()->nonaktif()->create(['nama' => 'Divisi Dibekukan']);

    $response = $this->getJson('/api/kiosk/divisions');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'nama'],
            ],
        ]);

    $data = $response->json('data');
    expect($data)->toHaveCount(2);

    $item = $data[0];
    expect(array_keys($item))->toEqualCanonicalizing(['id', 'nama'])
        ->and($item)->not->toHaveKey('aktif');

    $namaList = collect($data)->pluck('nama')->all();
    expect($namaList)->toBe(['Akademik', 'Umum'])
        ->and($namaList)->not->toContain('Divisi Dibekukan');
});
