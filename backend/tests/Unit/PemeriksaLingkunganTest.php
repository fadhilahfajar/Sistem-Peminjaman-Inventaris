<?php

namespace Tests\Unit;

use App\Support\PemeriksaLingkungan;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PemeriksaLingkunganTest extends TestCase
{
    #[DataProvider('versiMysql')]
    public function test_memeriksa_jenis_dan_versi_server(
        string $version,
        string $statusJenis,
        string $statusVersi
    ): void {
        $hasil = (new PemeriksaLingkungan)->periksa([
            'version' => $version,
            'sql_mode' => 'STRICT_TRANS_TABLES',
            'character_set_database' => 'utf8mb4',
        ]);

        $this->assertSame($statusJenis, $hasil['pemeriksaan'][0]['status']);
        $this->assertSame($statusVersi, $hasil['pemeriksaan'][1]['status']);
    }

    public static function versiMysql(): array
    {
        return [
            'versi di bawah minimum' => ['8.0.15', 'lulus', 'gagal'],
            'versi minimum' => ['8.0.16', 'lulus', 'lulus'],
            'versi MySQL modern' => ['8.4.10', 'lulus', 'lulus'],
            'MariaDB tidak diterima' => ['10.11.6-MariaDB', 'gagal', 'gagal'],
        ];
    }

    #[DataProvider('modeSql')]
    public function test_memeriksa_mode_sql(string $modeSql, string $status): void
    {
        $hasil = (new PemeriksaLingkungan)->periksa([
            'version' => '8.4.10',
            'sql_mode' => $modeSql,
            'character_set_database' => 'utf8mb4',
        ]);

        $this->assertSame($status, $hasil['pemeriksaan'][2]['status']);
    }

    public static function modeSql(): array
    {
        return [
            'mode strict tidak ada' => ['ONLY_FULL_GROUP_BY', 'gagal'],
            'mode strict ada' => ['ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_DATE', 'lulus'],
        ];
    }

    #[DataProvider('charsetDatabase')]
    public function test_memeriksa_charset_database(string $charset, string $status): void
    {
        $hasil = (new PemeriksaLingkungan)->periksa([
            'version' => '8.4.10',
            'sql_mode' => 'STRICT_TRANS_TABLES',
            'character_set_database' => $charset,
        ]);

        $this->assertSame($status, $hasil['pemeriksaan'][3]['status']);
    }

    public static function charsetDatabase(): array
    {
        return [
            'charset latin1' => ['latin1', 'gagal'],
            'charset utf8mb4' => ['utf8mb4', 'lulus'],
        ];
    }

    public function test_semua_pemeriksaan_lulus(): void
    {
        $hasil = (new PemeriksaLingkungan)->periksa([
            'version' => '8.4.10',
            'sql_mode' => 'STRICT_TRANS_TABLES',
            'character_set_database' => 'utf8mb4',
        ]);

        $this->assertSame('lulus', $hasil['status']);
        $this->assertCount(4, $hasil['pemeriksaan']);
    }

    public function test_satu_pemeriksaan_gagal_menggagalkan_hasil_keseluruhan(): void
    {
        $hasil = (new PemeriksaLingkungan)->periksa([
            'version' => '8.4.10',
            'sql_mode' => 'ONLY_FULL_GROUP_BY',
            'character_set_database' => 'utf8mb4',
        ]);

        $this->assertSame('gagal', $hasil['status']);
        $this->assertSame('gagal', $hasil['pemeriksaan'][2]['status']);
    }
}
