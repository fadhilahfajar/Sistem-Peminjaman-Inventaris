<?php

namespace App\Services;

use App\Models\Setting;
use InvalidArgumentException;
use RuntimeException;

class PengaturanService
{
    /**
     * Rentang min dan maks bersifat sementara (usulan B, belum dikonfirmasi A).
     * Hanya batas atas sesi_idle_menit yang berdasar DESAIN (SESSION_LIFETIME 120).
     */
    public const ATURAN = [
        'maks_durasi_awal_hari' => ['bawaan' => 7, 'min' => 1, 'maks' => 30],
        'maks_perpanjangan' => ['bawaan' => 2, 'min' => 0, 'maks' => 10],
        'maks_hari_per_perpanjangan' => ['bawaan' => 7, 'min' => 1, 'maks' => 30],
        'maks_booking_hari' => ['bawaan' => 7, 'min' => 0, 'maks' => 30],
        'sesi_idle_menit' => ['bawaan' => 15, 'min' => 1, 'maks' => 120],
        'kiosk_idle_detik' => ['bawaan' => 60, 'min' => 10, 'maks' => 600],
        'kiosk_lookup_maks_gagal' => ['bawaan' => 5, 'min' => 1, 'maks' => 20],
        'kiosk_lookup_kunci_menit' => ['bawaan' => 5, 'min' => 1, 'maks' => 60],
    ];

    public function angka(string $kunci): int
    {
        $this->pastikanDikenal($kunci);

        $nilai = Setting::query()->whereKey($kunci)->value('nilai');

        if ($nilai === null || ! is_numeric($nilai)) {
            throw new RuntimeException("Pengaturan '{$kunci}' belum diisi atau bukan angka. Jalankan seeder.");
        }

        return (int) $nilai;
    }

    public function validasi(string $kunci, int $nilai): void
    {
        $this->pastikanDikenal($kunci);

        ['min' => $min, 'maks' => $maks] = self::ATURAN[$kunci];

        if ($nilai < $min || $nilai > $maks) {
            throw new InvalidArgumentException("Nilai {$kunci} harus antara {$min} dan {$maks}.");
        }
    }

    private function pastikanDikenal(string $kunci): void
    {
        if (! array_key_exists($kunci, self::ATURAN)) {
            throw new InvalidArgumentException("Kunci pengaturan '{$kunci}' tidak dikenal.");
        }
    }
}
