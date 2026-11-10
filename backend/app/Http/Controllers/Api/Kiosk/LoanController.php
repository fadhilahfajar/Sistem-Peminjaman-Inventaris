<?php

namespace App\Http\Controllers\Api\Kiosk;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kiosk\LookupPeminjamanRequest;
use App\Http\Requests\Kiosk\SimpanPeminjamanRequest;
use App\Http\Resources\LoanKioskResource;
use App\Models\Loan;
use App\Services\KartuPeminjamanService;
use App\Services\PembatasKodeKiosk;
use App\Services\PeminjamanService;
use App\Support\KodeAcak;
use Illuminate\Http\JsonResponse;

class LoanController extends Controller
{
    public function __construct(
        private readonly PeminjamanService $peminjaman,
    ) {}

    public function buat(SimpanPeminjamanRequest $request): JsonResponse
    {
        $loan = $this->peminjaman->buat($request->validated());

        return (new LoanKioskResource($loan))
            ->response()
            ->setStatusCode($loan->wasRecentlyCreated ? 201 : 200);
    }

    public function lookup(
        LookupPeminjamanRequest $request,
        PembatasKodeKiosk $pembatas,
        KartuPeminjamanService $kartuService,
    ): JsonResponse {
        $ip = (string) $request->ip();

        if ($sisaDetik = $pembatas->periksaTerkunci($ip)) {
            return $pembatas->responseTerkunci($sisaDetik);
        }

        $kode = strtoupper(trim((string) $request->validated('kode')));

        $formatValid = (bool) preg_match('/^PJM-['.KodeAcak::ALFABET.']{6}$/', $kode);

        if (! $formatValid) {
            $pembatas->catatGagal($ip);

            return response()->json([
                'kode' => 'kode_tidak_ditemukan',
                'pesan' => 'Kode peminjaman tidak ditemukan.',
            ], 404);
        }

        $loan = Loan::query()->where('kode_pinjam', $kode)->first();

        if ($loan === null) {
            $pembatas->catatGagal($ip);

            return response()->json([
                'kode' => 'kode_tidak_ditemukan',
                'pesan' => 'Kode peminjaman tidak ditemukan.',
            ], 404);
        }

        $pembatas->catatSukses($ip);

        $kartu = $kartuService->bangun($loan);

        return response()->json([
            'data' => $kartu,
        ], 200);
    }
}
