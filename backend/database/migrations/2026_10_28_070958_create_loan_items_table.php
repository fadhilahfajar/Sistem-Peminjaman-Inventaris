<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_items', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->increments('id');
            $table->unsignedInteger('loan_id');
            $table->unsignedInteger('item_id');
            $table->integer('qty_dipinjam');

            $table->unique(['loan_id', 'item_id']);

            $table->foreign('loan_id')->references('id')->on('loans')->restrictOnDelete();
            $table->foreign('item_id')->references('id')->on('items')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE loan_items ADD CONSTRAINT loan_items_qty_check CHECK (qty_dipinjam >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_items');
    }
};
