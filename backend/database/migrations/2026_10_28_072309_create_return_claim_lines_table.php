<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_claim_lines', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->increments('id');
            $table->unsignedInteger('claim_id');
            $table->unsignedInteger('loan_item_id');
            $table->enum('kondisi_dilaporkan', ['baik', 'rusak', 'hilang']);
            $table->integer('qty');
            $table->integer('qty_baik')->nullable();
            $table->integer('qty_rusak')->nullable();
            $table->integer('qty_hilang')->nullable();
            $table->integer('qty_ditolak')->nullable();

            $table->unique(['claim_id', 'loan_item_id', 'kondisi_dilaporkan'], 'rcl_klaim_item_kondisi_unique');

            $table->foreign('claim_id')->references('id')->on('return_claims')->restrictOnDelete();
            $table->foreign('loan_item_id')->references('id')->on('loan_items')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE return_claim_lines ADD CONSTRAINT return_claim_lines_qty_check CHECK (qty >= 1)');

        DB::statement('ALTER TABLE return_claim_lines ADD CONSTRAINT return_claim_lines_hasil_check CHECK ('
            .'(qty_baik IS NULL AND qty_rusak IS NULL AND qty_hilang IS NULL AND qty_ditolak IS NULL) '
            .'OR (qty_baik IS NOT NULL AND qty_rusak IS NOT NULL AND qty_hilang IS NOT NULL AND qty_ditolak IS NOT NULL '
            .'AND qty_baik >= 0 AND qty_rusak >= 0 AND qty_hilang >= 0 AND qty_ditolak >= 0 '
            .'AND qty_baik + qty_rusak + qty_hilang + qty_ditolak = qty))');
    }

    public function down(): void
    {
        Schema::dropIfExists('return_claim_lines');
    }
};
