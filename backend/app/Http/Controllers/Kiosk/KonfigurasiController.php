<?php

namespace App\Http\Controllers\Kiosk;

use App\Http\Controllers\Controller;
use App\Services\PengaturanService;
use Illuminate\Http\JsonResponse;

class KonfigurasiController extends Controller
{
    /** Hanya parameter yang dibutuhkan kiosk (DESAIN 6). Parameter keamanan tidak dibuka. */
    private const KUNCI_PUBLIK = [
        'maks_durasi_awal_hari',
        'maks_booking_hari',
        'maks_perpanjangan',
        'maks_hari_per_perpanjangan',
        'kiosk_idle_detik',
    ];

    public function tampil(PengaturanService $pengaturan): JsonResponse
    {
        $data = [];
        foreach (self::KUNCI_PUBLIK as $kunci) {
            $data[$kunci] = $pengaturan->angka($kunci);
        }

        return response()->json(['data' => $data]);
    }
}
