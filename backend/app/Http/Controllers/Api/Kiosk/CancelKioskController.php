<?php

namespace App\Http\Controllers\Api\Kiosk;

use App\Exceptions\GalatBisnis;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kiosk\SimpanPembatalanRequest;
use App\Models\Loan;
use App\Services\PembatalanService;
use App\Services\PembatasKodeKiosk;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class CancelKioskController extends Controller
{
    public function __construct(
        private readonly PembatalanService $pembatalan,
    ) {}

    public function batalkan(
        SimpanPembatalanRequest $request,
        PembatasKodeKiosk $pembatas,
    ): JsonResponse {
        $ip = (string) $request->ip();

        if ($sisaDetik = $pembatas->periksaTerkunci($ip)) {
            return $pembatas->responseTerkunci($sisaDetik);
        }

        $kode = strtoupper(trim((string) $request->validated('kode_pinjam')));

        // Periksa keberadaan loan sebelum Service agar bisa catatGagal pembatas kode.
        // Gunakan exists() untuk notasi baca-saja, lalu catatGagal jika tidak ditemukan.
        if (! Loan::query()->where('kode_pinjam', $kode)->exists()) {
            $pembatas->catatGagal($ip);

            return response()->json([
                'kode' => 'kode_tidak_ditemukan',
                'pesan' => 'Kode peminjaman tidak ditemukan.',
            ], 404);
        }

        try {
            $loan = $this->pembatalan->olehPeminjam($kode);
        } catch (GalatBisnis $e) {
            // Galat aturan bisnis dari Service (409 dan 422) diteruskan apa adanya
            // TIDAK dihitung sebagai percobaan tebak (tidak memicu catatGagal).
            return response()->json([
                'kode' => $e->kode,
                'pesan' => $e->pesan,
            ], 409);
        } catch (ValidationException $e) {
            // Galat validasi dari service (422)
            return response()->json([
                'kode' => 'validasi_gagal',
                'pesan' => $e->getMessage(),
            ], 422);
        }

        $pembatas->catatSukses($ip);

        $response = [
            'data' => [
                'kode_pinjam' => $loan->kode_pinjam,
                'status' => 'dibatalkan',
                'dibatalkan_pada' => $loan->dibatalkan_pada?->toIso8601String(),
            ],
        ];

        return response()->json($response, 200);
    }
}
