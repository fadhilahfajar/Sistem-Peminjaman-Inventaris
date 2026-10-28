<?php

namespace Tests\Unit;

use App\Support\KodeAcak;
use PHPUnit\Framework\TestCase;

class KodeAcakTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Konstanta alfabet
    // -----------------------------------------------------------------------

    public function test_alfabet_tepat_31_karakter(): void
    {
        $this->assertSame(31, strlen(KodeAcak::ALFABET));
    }

    public function test_alfabet_tidak_memuat_karakter_ambigu(): void
    {
        // Karakter yang harus absen: I, L, O (huruf besar), 0, 1
        foreach (['I', 'L', 'O', '0', '1'] as $karakter) {
            $this->assertStringNotContainsString(
                $karakter,
                KodeAcak::ALFABET,
                "Karakter '{$karakter}' tidak boleh ada dalam alfabet."
            );
        }
    }

    public function test_alfabet_hanya_huruf_besar_dan_angka(): void
    {
        $this->assertMatchesRegularExpression('/^[A-Z0-9]+$/', KodeAcak::ALFABET);
    }

    // -----------------------------------------------------------------------
    // kodePinjam()
    // -----------------------------------------------------------------------

    public function test_kode_pinjam_berformat_pjm_xxxxxx(): void
    {
        $kode = KodeAcak::kodePinjam();

        $this->assertMatchesRegularExpression('/^PJM-[A-Z2-9]{6}$/', $kode);
    }

    public function test_kode_pinjam_diawali_pjm(): void
    {
        $kode = KodeAcak::kodePinjam();

        $this->assertStringStartsWith('PJM-', $kode);
    }

    public function test_kode_pinjam_panjang_total_sepuluh_karakter(): void
    {
        // "PJM-" (4) + 6 karakter acak = 10
        $this->assertSame(10, strlen(KodeAcak::kodePinjam()));
    }

    public function test_kode_pinjam_hanya_berisi_karakter_alfabet_yang_diizinkan(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $kode = KodeAcak::kodePinjam();
            $bagianAcak = substr($kode, 4); // setelah "PJM-"

            $this->assertSame(
                6,
                strlen($bagianAcak),
                "Bagian acak harus 6 karakter, dapat: '{$bagianAcak}'"
            );

            foreach (str_split($bagianAcak) as $karakter) {
                $this->assertStringContainsString(
                    $karakter,
                    KodeAcak::ALFABET,
                    "Karakter '{$karakter}' tidak ada dalam alfabet yang diizinkan."
                );
            }
        }
    }

    public function test_kode_pinjam_tidak_memuat_karakter_ambigu(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $bagianAcak = substr(KodeAcak::kodePinjam(), 4);

            foreach (['I', 'L', 'O', '0', '1'] as $karakter) {
                $this->assertStringNotContainsString(
                    $karakter,
                    $bagianAcak,
                    "Karakter ambigu '{$karakter}' ditemukan dalam kode pinjam."
                );
            }
        }
    }

    // -----------------------------------------------------------------------
    // kodeKlaim()
    // -----------------------------------------------------------------------

    public function test_kode_klaim_berformat_kmb_xxxxxx(): void
    {
        $kode = KodeAcak::kodeKlaim();

        $this->assertMatchesRegularExpression('/^KMB-[A-Z2-9]{6}$/', $kode);
    }

    public function test_kode_klaim_diawali_kmb(): void
    {
        $kode = KodeAcak::kodeKlaim();

        $this->assertStringStartsWith('KMB-', $kode);
    }

    public function test_kode_klaim_panjang_total_sepuluh_karakter(): void
    {
        // "KMB-" (4) + 6 karakter acak = 10
        $this->assertSame(10, strlen(KodeAcak::kodeKlaim()));
    }

    public function test_kode_klaim_hanya_berisi_karakter_alfabet_yang_diizinkan(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $kode = KodeAcak::kodeKlaim();
            $bagianAcak = substr($kode, 4); // setelah "KMB-"

            $this->assertSame(
                6,
                strlen($bagianAcak),
                "Bagian acak harus 6 karakter, dapat: '{$bagianAcak}'"
            );

            foreach (str_split($bagianAcak) as $karakter) {
                $this->assertStringContainsString(
                    $karakter,
                    KodeAcak::ALFABET,
                    "Karakter '{$karakter}' tidak ada dalam alfabet yang diizinkan."
                );
            }
        }
    }

    public function test_kode_klaim_tidak_memuat_karakter_ambigu(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $bagianAcak = substr(KodeAcak::kodeKlaim(), 4);

            foreach (['I', 'L', 'O', '0', '1'] as $karakter) {
                $this->assertStringNotContainsString(
                    $karakter,
                    $bagianAcak,
                    "Karakter ambigu '{$karakter}' ditemukan dalam kode klaim."
                );
            }
        }
    }

    // -----------------------------------------------------------------------
    // Variasi antar panggilan
    // -----------------------------------------------------------------------

    /**
     * Dua panggilan berturutan sangat jarang menghasilkan nilai yang sama
     * (peluang bertabrakan ≈ 1 dalam 887 juta). Tes ini hanya memverifikasi
     * bahwa generator tidak statis/konstan.
     */
    public function test_dua_kode_pinjam_berturutan_berbeda(): void
    {
        $kode1 = KodeAcak::kodePinjam();
        $kode2 = KodeAcak::kodePinjam();

        // Dengan 887 juta kemungkinan, kemungkinan sama ≈ 0. Jalankan beberapa kali.
        $adaBeda = false;
        for ($i = 0; $i < 10; $i++) {
            if (KodeAcak::kodePinjam() !== KodeAcak::kodePinjam()) {
                $adaBeda = true;
                break;
            }
        }

        // Setidaknya dua dari sepuluh pasang pasti berbeda.
        $this->assertTrue($adaBeda, 'Generator tampak menghasilkan nilai konstan.');
    }

    public function test_kode_pinjam_dan_klaim_berbeda_prefix(): void
    {
        $pinjam = KodeAcak::kodePinjam();
        $klaim = KodeAcak::kodeKlaim();

        $this->assertStringStartsWith('PJM-', $pinjam);
        $this->assertStringStartsWith('KMB-', $klaim);
        $this->assertNotSame(substr($pinjam, 0, 3), substr($klaim, 0, 3));
    }
}
