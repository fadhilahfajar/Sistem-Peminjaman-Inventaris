<?php

namespace App\Support;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/** Bentuk galat JSON seragam {kode, pesan, errors?} untuk seluruh API (DESAIN 6 dan 8). */
class PenangananGalat
{
    private const KODE = [
        401 => ['tidak_terautentikasi', 'Anda belum masuk atau sesi berakhir.'],
        403 => ['dilarang', 'Anda tidak berhak melakukan aksi ini.'],
        404 => ['tidak_ditemukan', 'Data atau halaman tidak ditemukan.'],
        409 => ['konflik', 'Data berubah atau sudah diproses. Muat ulang lalu coba lagi.'],
        419 => ['sesi_kedaluwarsa', 'Sesi berakhir, silakan masuk lagi.'],
        422 => ['validasi_gagal', 'Data yang dikirim tidak valid.'],
        429 => ['terlalu_banyak_percobaan', 'Terlalu banyak percobaan. Coba lagi beberapa saat lagi.'],
        500 => ['galat_server', 'Terjadi kesalahan pada server. Coba lagi.'],
    ];

    public static function berlaku(Request $request): bool
    {
        return $request->is('api/*') || $request->is('sanctum/*') || $request->expectsJson();
    }

    public static function tangani(Throwable $e, Request $request): ?JsonResponse
    {
        if (! self::berlaku($request)) {
            return null;
        }

        if ($e instanceof ValidationException) {
            return self::balas(422, $e->errors());
        }

        if ($e instanceof AuthenticationException) {
            return self::balas(401);
        }

        $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
        $headers = $e instanceof HttpExceptionInterface ? $e->getHeaders() : [];

        if (! isset(self::KODE[$status])) {
            $status = $status >= 500 ? 500 : $status;
        }

        return self::balas($status, null, $headers);
    }

    private static function balas(int $status, ?array $errors = null, array $headers = []): JsonResponse
    {
        [$kode, $pesan] = self::KODE[$status] ?? ['galat_permintaan', 'Permintaan tidak dapat diproses.'];

        $isi = ['kode' => $kode, 'pesan' => $pesan];

        if ($errors !== null) {
            $isi['errors'] = $errors;
        }

        // Hanya header yang aman dan perlu (mis. Retry-After pada 429).
        $aman = array_intersect_key($headers, array_flip(['Retry-After', 'X-RateLimit-Limit', 'X-RateLimit-Remaining']));

        return response()->json($isi, $status, $aman);
    }
}
