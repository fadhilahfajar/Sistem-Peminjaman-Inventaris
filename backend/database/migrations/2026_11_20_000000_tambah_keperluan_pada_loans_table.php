<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('loans', 'keperluan')) {
            return;
        }

        Schema::table('loans', function (Blueprint $table): void {
            $table->string('keperluan', 255)->nullable()->after('telepon');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('loans', 'keperluan')) {
            return;
        }

        Schema::table('loans', function (Blueprint $table): void {
            $table->dropColumn('keperluan');
        });
    }
};
