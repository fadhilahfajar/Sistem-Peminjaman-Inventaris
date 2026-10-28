<?php

namespace Tests\Unit;

use App\Support\Tanggal;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TanggalTest extends TestCase
{
    // -----------------------------------------------------------------------
    // hariIni()
    // -----------------------------------------------------------------------

    /**
     * Tepat sebelum tengah malam Jakarta (23:59:59 WIB = 16:59:59 UTC):
     * hariIni() harus mengembalikan tanggal hari yang SAMA dengan Jakarta.
     */
    public function test_hari_ini_sebelum_tengah_malam_jakarta(): void
    {
        // 2026-03-01 23:59:59 WIB = 2026-03-01 16:59:59 UTC
        $sekarang = new DateTimeImmutable('2026-03-01 16:59:59', new DateTimeZone('UTC'));

        $this->assertSame('2026-03-01', Tanggal::hariIni($sekarang));
    }

    /**
     * Tepat setelah tengah malam Jakarta (00:00:01 WIB = 17:00:01 UTC hari sebelumnya):
     * hariIni() harus mengembalikan tanggal hari BERIKUTNYA di Jakarta.
     */
    public function test_hari_ini_sesudah_tengah_malam_jakarta(): void
    {
        // 2026-03-02 00:00:01 WIB = 2026-03-01 17:00:01 UTC
        $sekarang = new DateTimeImmutable('2026-03-01 17:00:01', new DateTimeZone('UTC'));

        $this->assertSame('2026-03-02', Tanggal::hariIni($sekarang));
    }

    /**
     * Persis tengah malam Jakarta (00:00:00 WIB = 17:00:00 UTC hari sebelumnya):
     * harus berubah ke hari baru.
     */
    public function test_hari_ini_tepat_tengah_malam_jakarta(): void
    {
        // 2026-06-15 00:00:00 WIB = 2026-06-14 17:00:00 UTC
        $sekarang = new DateTimeImmutable('2026-06-14 17:00:00', new DateTimeZone('UTC'));

        $this->assertSame('2026-06-15', Tanggal::hariIni($sekarang));
    }

    /**
     * UTC dan Jakarta berbeda 7 jam. Jam 10 UTC masih tanggal kemarin di Jakarta
     * hanya bila UTC sudah pindah hari tapi Jakarta belum — verifikasi sebaliknya.
     * Misalnya: 2026-07-20 10:00:00 UTC = 2026-07-20 17:00:00 WIB (hari sama).
     */
    public function test_hari_ini_berbeda_dengan_utc_saat_mendekati_tengah_malam_utc(): void
    {
        // 2026-07-20 23:30:00 UTC = 2026-07-21 06:30:00 WIB (masih hari berikutnya di Jakarta)
        $sekarang = new DateTimeImmutable('2026-07-20 23:30:00', new DateTimeZone('UTC'));

        // WIB = UTC+7, jadi 23:30 UTC = 06:30 WIB keesokan harinya
        $this->assertSame('2026-07-21', Tanggal::hariIni($sekarang));
    }

    public function test_hari_ini_tanpa_argumen_mengembalikan_format_y_m_d(): void
    {
        $hasil = Tanggal::hariIni();

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $hasil);
    }

    // -----------------------------------------------------------------------
    // selisihHari()
    // -----------------------------------------------------------------------

    public function test_selisih_hari_tanggal_sama_adalah_nol(): void
    {
        $this->assertSame(0, Tanggal::selisihHari('2026-03-15', '2026-03-15'));
    }

    public function test_selisih_hari_maju_satu_hari(): void
    {
        $this->assertSame(1, Tanggal::selisihHari('2026-03-15', '2026-03-16'));
    }

    public function test_selisih_hari_mundur_satu_hari(): void
    {
        $this->assertSame(-1, Tanggal::selisihHari('2026-03-16', '2026-03-15'));
    }

    #[DataProvider('selisihHariProvider')]
    public function test_selisih_hari(string $pertama, string $kedua, int $diharapkan): void
    {
        $this->assertSame($diharapkan, Tanggal::selisihHari($pertama, $kedua));
    }

    public static function selisihHariProvider(): array
    {
        return [
            'tujuh hari maju' => ['2026-01-01', '2026-01-08', 7],
            'tujuh hari mundur' => ['2026-01-08', '2026-01-01', -7],
            'lintas bulan maju' => ['2026-01-31', '2026-02-01', 1],
            'lintas bulan mundur' => ['2026-02-01', '2026-01-31', -1],
            'lintas tahun maju' => ['2025-12-31', '2026-01-01', 1],
            'lintas tahun mundur' => ['2026-01-01', '2025-12-31', -1],
            'tahun kabisat maju' => ['2024-02-28', '2024-02-29', 1],
            'tiga puluh hari' => ['2026-03-01', '2026-03-31', 30],
        ];
    }

    /**
     * Selisih hari harus berupa hari kalender murni, bukan jam.
     * Pastikan tidak terpengaruh offset zona waktu saat parsing.
     */
    public function test_selisih_hari_adalah_hari_kalender_bukan_durasi_jam(): void
    {
        // Pergantian dari tanggal akhir bulan ke awal bulan berikutnya: tepat 1 hari
        $this->assertSame(1, Tanggal::selisihHari('2026-04-30', '2026-05-01'));
        $this->assertSame(-1, Tanggal::selisihHari('2026-05-01', '2026-04-30'));
    }
}
