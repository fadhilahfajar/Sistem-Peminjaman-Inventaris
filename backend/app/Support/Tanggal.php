<?php

namespace App\Support;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Utilitas tanggal kalender zona Asia/Jakarta.
 *
 * Aturan:
 * - Tanggal kalender selalu dalam zona Asia/Jakarta (BR-21).
 * - Selisih hari dihitung dari dua nilai Y-m-d, bukan dari timestamp atau durasi jam,
 *   sehingga pergantian malam tidak menggeser tanggal (DESAIN bagian 6).
 */
class Tanggal
{
    private const ZONA = 'Asia/Jakarta';

    /**
     * Kembalikan tanggal hari ini dalam format Y-m-d berdasarkan zona Asia/Jakarta.
     *
     * Menerima argumen $sekarang untuk keperluan pengujian deterministik
     * (inject objek DateTimeImmutable dari zona UTC atau zona mana pun;
     * metode ini selalu mengonversinya ke Asia/Jakarta sebelum mengambil tanggal).
     */
    public static function hariIni(?DateTimeImmutable $sekarang = null): string
    {
        $zona = new DateTimeZone(self::ZONA);
        $waktu = ($sekarang ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))->setTimezone($zona);

        return $waktu->format('Y-m-d');
    }

    /**
     * Selisih hari kalender bertanda antara dua tanggal Y-m-d.
     *
     * Hasil = $tanggalKedua - $tanggalPertama (hari).
     * Nilai positif berarti $tanggalKedua lebih maju.
     * Nilai negatif berarti $tanggalKedua lebih awal.
     * Nilai nol berarti sama.
     *
     * Keduanya di-parse sebagai tengah malam pada zona yang sama agar
     * tidak ada geseran akibat perubahan DST atau perbedaan offset.
     */
    public static function selisihHari(string $tanggalPertama, string $tanggalKedua): int
    {
        $zona = new DateTimeZone(self::ZONA);

        $dt1 = new DateTimeImmutable($tanggalPertama, $zona);
        $dt2 = new DateTimeImmutable($tanggalKedua, $zona);

        // Normalisasi ke tengah malam agar diff hanya menghitung hari kalender.
        $dt1 = $dt1->setTime(0, 0, 0);
        $dt2 = $dt2->setTime(0, 0, 0);

        $diff = $dt1->diff($dt2);

        // $diff->invert = 1 berarti $dt2 < $dt1 (hasil negatif).
        return $diff->invert === 1 ? -$diff->days : $diff->days;
    }
}
