<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Catatan: Item factory memberi stok awal langsung tanpa buku besar (stock_movements).
 * Ini hanya untuk tes baca dan tidak boleh dipakai bersama inventaris:periksa-invarian.
 *
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    protected $model = Item::class;

    public function definition(): array
    {
        return [
            'nama' => fake()->unique()->words(3, true),
            'deskripsi' => fake()->sentence(),
            'kategori_id' => Category::factory(),
            'total' => 10,
            'tersedia' => 10,
            'rusak' => 0,
            'boleh_diperpanjang' => true,
        ];
    }

    public function habis(): static
    {
        return $this->state(fn () => [
            'total' => 5,
            'tersedia' => 0,
            'rusak' => 0,
        ]);
    }

    public function terhapus(): static
    {
        return $this->state(fn () => [
            'dihapus_pada' => now(),
        ]);
    }
}
