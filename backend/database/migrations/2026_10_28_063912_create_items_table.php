<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->increments('id');
            $table->string('nama', 150);
            $table->text('deskripsi')->nullable();
            $table->unsignedInteger('kategori_id');
            $table->integer('total');
            $table->integer('tersedia');
            $table->integer('rusak')->default(0);
            $table->boolean('boleh_diperpanjang')->default(true);
            $table->dateTime('dibuat_pada')->useCurrent();
            $table->dateTime('diubah_pada')->useCurrent()->useCurrentOnUpdate();
            $table->dateTime('dihapus_pada')->nullable();
            $table->string('nama_aktif', 150)->nullable()
                ->storedAs('IF(dihapus_pada IS NULL, nama, NULL)')
                ->unique();

            $table->foreign('kategori_id')->references('id')->on('categories')->restrictOnDelete();

        });
        DB::statement('ALTER TABLE items ADD CONSTRAINT items_total_check CHECK (total >= 0)');
        DB::statement('ALTER TABLE items ADD CONSTRAINT items_tersedia_check CHECK (tersedia >= 0)');
        DB::statement('ALTER TABLE items ADD CONSTRAINT items_rusak_check CHECK (rusak >= 0)');
        DB::statement('ALTER TABLE items ADD CONSTRAINT items_boleh_diperpanjang_check CHECK (boleh_diperpanjang IN (0, 1))');
        DB::statement('ALTER TABLE items ADD CONSTRAINT items_stok_check CHECK (tersedia + rusak <= total)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
