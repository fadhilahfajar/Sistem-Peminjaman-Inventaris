<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Division;
use Illuminate\Database\Seeder;

class DivisiKategoriSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['IT', 'Media', 'EO'] as $nama) {
            Division::firstOrCreate(['nama' => $nama], ['aktif' => true]);
        }

        Category::firstOrCreate(['nama' => 'Tanpa Kategori'], ['aktif' => true]);
    }
}
