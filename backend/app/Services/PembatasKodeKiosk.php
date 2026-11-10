<?php

namespace App\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Pembatas percobaan kode kiosk (K-6, BR-18, T-06).
 *
 * Menerapkan batas percobaan per IP dengan penguncian beruntun:
 * - Kunci per IP.
 * - Setiap kegagalan menaikkan penghitung.
 * - Mencapai kiosk_lookup_maks_gagal mengunci IP selama kiosk_lookup_kunci_menit.
 * - Sukses mereset penghitung dan penguncian.
 * - Penghitung kegagalan kedaluwarsa setelah kiosk_lookup_kunci_menit menit sejak kegagalan terakhir.
 * - Batas dibaca dinamis dari PengaturanService pada setiap permintaan.
 */
class PembatasKodeKiosk
{
    public function __construct(private PengaturanService $pengaturan) {}

    /**
     * Memeriksa apakah IP sedang terkunci.
     * Mengembalikan sisa detik jika terkunci, atau null jika tidak terkunci.
     */
    public function periksaTerkunci(string $ip): ?int
    {
        $kunciLock = "kiosk_lookup:lock:{$ip}";
        $terkunciSampai = Cache::get($kunciLock);

        if ($terkunciSampai === null) {
            return null;
        }

        $sisaDetik = (int) ($terkunciSampai - now()->timestamp);

        if ($sisaDetik <= 0) {
            Cache::forget($kunciLock);

            return null;
        }

        return $sisaDetik;
    }

    /**
     * Mencatat percobaan gagal untuk IP tertentu.
     */
    public function catatGagal(string $ip): void
    {
        $maksGagal = $this->pengaturan->angka('kiosk_lookup_maks_gagal');
        $kunciMenit = $this->pengaturan->angka('kiosk_lookup_kunci_menit');
        $kunciFail = "kiosk_lookup:fail:{$ip}";

        $gagal = (int) Cache::get($kunciFail, 0) + 1;

        if ($gagal >= $maksGagal) {
            $terkunciSampai = now()->addMinutes($kunciMenit)->timestamp;
            Cache::put("kiosk_lookup:lock:{$ip}", $terkunciSampai, now()->addMinutes($kunciMenit));
            Cache::forget($kunciFail);
        } else {
            Cache::put($kunciFail, $gagal, now()->addMinutes($kunciMenit));
        }
    }

    /**
     * Mencatat percobaan sukses, mereset kegagalan dan kunci.
     */
    public function catatSukses(string $ip): void
    {
        Cache::forget("kiosk_lookup:fail:{$ip}");
        Cache::forget("kiosk_lookup:lock:{$ip}");
    }

    /**
     * Respons seragam 429 saat terkunci.
     */
    public function responseTerkunci(int $sisaDetik): JsonResponse
    {
        $menit = (int) ceil($sisaDetik / 60);

        return response()->json([
            'kode' => 'lookup_terkunci',
            'pesan' => "Terlalu banyak percobaan, coba lagi dalam {$menit} menit.",
        ], 429, [
            'Retry-After' => (string) $sisaDetik,
        ]);
    }
}
