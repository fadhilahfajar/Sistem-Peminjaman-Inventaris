<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PDOException;
use Tests\TestCase;

class PeriksaLingkunganCommandTest extends TestCase
{
    public function test_it_reports_results_and_returns_success_without_opening_a_connection(): void
    {
        DB::shouldReceive('selectOne')
            ->once()
            ->with('SELECT VERSION() AS version, @@global.sql_mode AS sql_mode, @@character_set_database AS charset')
            ->andReturn((object) [
                'version' => '8.4.10',
                'sql_mode' => 'STRICT_TRANS_TABLES',
                'charset' => 'utf8mb4',
            ]);

        $this->artisan('inventaris:periksa-lingkungan')
            ->expectsOutputToContain('sql_mode server (global): LULUS')
            ->expectsOutputToContain('Semua pemeriksaan lingkungan lulus.')
            ->assertExitCode(0);
    }

    public function test_it_hides_connection_details_when_the_query_fails(): void
    {
        DB::shouldReceive('selectOne')
            ->once()
            ->andThrow(new QueryException(
                'mysql',
                'SELECT VERSION()',
                [],
                new PDOException('Sensitive connection detail')
            ));

        $this->artisan('inventaris:periksa-lingkungan')
            ->expectsOutputToContain('Koneksi database gagal. Periksa konfigurasi dan ketersediaan server.')
            ->doesntExpectOutputToContain('Sensitive connection detail')
            ->assertExitCode(1);
    }
}
