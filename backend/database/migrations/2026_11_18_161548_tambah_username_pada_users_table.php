<?php

use App\Support\BuatUsernameDariEmail;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('username', 50)->nullable()->after('name');
        });

        $dipakai = [];

        DB::table('users')
            ->orderBy('id')
            ->get(['id', 'email'])
            ->each(function ($row) use (&$dipakai): void {
                $username = BuatUsernameDariEmail::dariEmail(
                    (string) ($row->email ?? ''),
                    (int) $row->id,
                    fn (string $u): bool => isset($dipakai[$u]),
                );

                $dipakai[$username] = true;

                DB::table('users')
                    ->where('id', $row->id)
                    ->update(['username' => $username]);
            });

        // NOT NULL + UNIQUE untuk username; email menjadi nullable (UNIQUE tetap).
        DB::statement('ALTER TABLE users MODIFY username VARCHAR(50) COLLATE utf8mb4_unicode_ci NOT NULL');
        DB::statement('ALTER TABLE users ADD UNIQUE users_username_unique (username)');
        DB::statement('ALTER TABLE users MODIFY email VARCHAR(190) COLLATE utf8mb4_unicode_ci NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP INDEX users_username_unique');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('username');
        });
        DB::statement('ALTER TABLE users MODIFY email VARCHAR(190) COLLATE utf8mb4_unicode_ci NOT NULL');
    }
};
