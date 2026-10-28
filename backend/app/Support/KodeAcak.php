<?php

namespace App\Support;

/**
 * Generator kode acak kriptografis untuk kode pinjam dan kode klaim.
 *
 * Alfabet 31 karakter tanpa karakter ambigu I, L, O, 0, 1 (BR-17).
 * Menggunakan random_int() agar kriptografis aman.
 * Tidak ada akses database atau pemeriksaan keunikan di kelas ini;
 * keunikan dijamin oleh constraint UNIQUE pada kolom tabel.
 */
class KodeAcak
{
    /** Alfabet 31 karakter tanpa I, L, O, 0, 1 (BR-17). */
    public const ALFABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    private const PANJANG = 6;

    private const PREFIX_PINJAM = 'PJM';

    private const PREFIX_KLAIM = 'KMB';

    /**
     * Bangkitkan enam karakter acak dari alfabet 31 karakter.
     */
    public static function acak(): string
    {
        $maks = strlen(self::ALFABET) - 1;
        $hasil = '';

        for ($i = 0; $i < self::PANJANG; $i++) {
            $hasil .= self::ALFABET[random_int(0, $maks)];
        }

        return $hasil;
    }

    /**
     * Bangkitkan kode pinjam dengan format PJM-XXXXXX.
     */
    public static function kodePinjam(): string
    {
        return self::PREFIX_PINJAM.'-'.self::acak();
    }

    /**
     * Bangkitkan kode klaim dengan format KMB-XXXXXX.
     */
    public static function kodeKlaim(): string
    {
        return self::PREFIX_KLAIM.'-'.self::acak();
    }
}
