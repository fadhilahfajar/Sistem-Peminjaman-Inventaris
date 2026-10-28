<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_claims', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->increments('id');
            $table->string('kode_klaim', 12)->unique();
            $table->unsignedInteger('loan_id');
            $table->string('catatan_peminjam', 500)->nullable();
            $table->dateTime('diklaim_pada')->useCurrent();
            $table->integer('hari_terlambat')->default(0);
            $table->enum('status', ['menunggu', 'diverifikasi'])->default('menunggu');
            $table->unsignedInteger('diverifikasi_oleh')->nullable();
            $table->dateTime('diverifikasi_pada')->nullable();
            $table->string('catatan_admin', 500)->nullable();
            $table->char('idempotency_key', 36)->nullable()->unique();

            $table->index(['status', 'diklaim_pada']);

            $table->foreign('loan_id')->references('id')->on('loans')->restrictOnDelete();
            $table->foreign('diverifikasi_oleh')->references('id')->on('users')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE return_claims ADD CONSTRAINT return_claims_hari_terlambat_check CHECK (hari_terlambat >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('return_claims');
    }
};
