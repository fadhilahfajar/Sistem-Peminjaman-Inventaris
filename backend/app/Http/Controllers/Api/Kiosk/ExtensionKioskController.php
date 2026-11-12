<?php

namespace App\Http\Controllers\Api\Kiosk;

use App\Exceptions\GalatBisnis;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kiosk\SimpanPerpanjanganRequest;
use App\Models\Loan;
use App\Services\PembatasKodeKiosk;
use App\Services\PengaturanService;
use App\Services\PerpanjanganService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ExtensionKioskController extends Controller
{
    public function __construct(
        private readonly PerpanjanganService $perpanjangan,
    ) {}

    public function simpan(
        SimpanPerpanjanganRequest $request,
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
            $loan = $this->perpanjangan->olehPeminjam(
                $kode,
                (int) $request->validated('tambahan_hari'),
                $request->validated('alasan'),
            );
        } catch (GalatBisnis $e) {
            // Galat aturan bisnis dari Service (409 dan 422) diteruskan apa adanya
            // TIDAK dihitung sebagai percobaan tebak (tidak memicu catatGagal).
            return response()->json([
                'kode' => $e->kode,
                'pesan' => $e->pesan,
            ], 409);
        } catch (ValidationException $e) {
            // Galat validasi dari validasiHari di service (422)
            return response()->json([
                'kode' => 'validasi_gagal',
                'pesan' => $e->getMessage(),
            ], 422);
        }

        $pembatas->catatSukses($ip);

        // Baca batas kembali sebelumnya dari loan_extensions terbaru (baca-saja)
        $batasLama = $loan->batas_kembali->format('Y-m-d');
        $terpakai = $loan->extensions->count();

        $response = [
            'data' => [
                'kode_pinjam' => $loan->kode_pinjam,
                'batas_kembali_sebelumnya' => $batasLama,
                'batas_kembali' => $loan->batas_kembali->format('Y-m-d'),
                'tambahan_hari' => $loan->extensions->last()?->tambahan_hari ?? $request->validated('tambahan_hari'),
                'perpanjangan' => [
                    'terpakai' => $terpakai,
                    'maks' => app(PengaturanService::class)->angka('maks_perpanjangan'),
                    'sisa_kuota' => app(PengaturanService::class)->angka('maks_perpanjangan') - $terpakai,
                ],
                'status' => $loan->status,
            ],
        ];

        return response()->json($response, 200);
    }
}
