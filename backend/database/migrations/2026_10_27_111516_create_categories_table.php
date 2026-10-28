<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->increments('id');
            $table->string('nama', 100)->unique();
            $table->boolean('aktif')->default(true);
        });

        DB::statement('ALTER TABLE categories ADD CONSTRAINT categories_aktif_check CHECK (aktif IN (0, 1))');
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
