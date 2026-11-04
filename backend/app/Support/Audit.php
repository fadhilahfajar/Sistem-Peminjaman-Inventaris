<?php

namespace App\Support;

use App\Models\AuditLog;
use InvalidArgumentException;

class Audit
{
    private const KATA_TERLARANG = ['password', 'sandi', 'token', 'secret'];

    public static function catat(
        string $aksi,
        string $entitas,
        ?int $entitasId = null,
        ?int $userId = null,
        ?array $detail = null,
    ): AuditLog {
        if ($detail !== null) {
            self::periksa($detail);
        }

        return AuditLog::create([
            'user_id' => $userId,
            'aksi' => $aksi,
            'entitas' => $entitas,
            'entitas_id' => $entitasId,
            'detail' => $detail,
        ]);
    }

    private static function periksa(array $data): void
    {
        foreach ($data as $kunci => $nilai) {
            if (is_string($kunci) && self::terlarang($kunci)) {
                throw new InvalidArgumentException("Audit tidak boleh memuat kunci '{$kunci}'.");
            }

            if (is_array($nilai)) {
                self::periksa($nilai);
            }
        }
    }

    private static function terlarang(string $kunci): bool
    {
        $kunci = mb_strtolower($kunci);

        foreach (self::KATA_TERLARANG as $kata) {
            if (str_contains($kunci, $kata)) {
                return true;
            }
        }

        return false;
    }
}
