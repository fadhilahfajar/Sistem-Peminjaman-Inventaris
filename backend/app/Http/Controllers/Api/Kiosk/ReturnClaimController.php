<?php

namespace App\Http\Controllers\Api\Kiosk;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kiosk\SimpanKlaimRequest;
use App\Models\Loan;
use App\Models\ReturnClaim;
use App\Services\KlaimService;
use App\Services\PembatasKodeKiosk;
use App\Support\KodeAcak;
use Illuminate\Http\JsonResponse;

class ReturnClaimController extends Controller
{
    public function __construct(
        private readonly KlaimService $klaim,
    ) {}

    public function buat(
        SimpanKlaimRequest $request,
        PembatasKodeKiosk $pembatas,
    ): JsonResponse {
        $ip = (string) $request->ip();

        if ($sisaDetik = $pembatas->periksaTerkunci($ip)) {
            return $pembatas->responseTerkunci($sisaDetik);
        }

        $kode = strtoupper(trim((string) $request->validated('kode')));

        // Periksa keberadaan loan sebelum Service agar bisa catat gagal pembatas kode.
        // KlaimService::simpan pakai firstOrFail() yang melempar ModelNotFoundException —
        // tidak membedakan "kode tidak ditemukan" dari galat lain, jadi pemeriksaan di sini.
        $formatValid = (bool) preg_match('/^PJM-['.KodeAcak::ALFABET.']{6}$/', $kode);

        if (! $formatValid || ! Loan::query()->where('kode_pinjam', $kode)->exists()) {
            $pembatas->catatGagal($ip);

            return response()->json([
                'kode' => 'kode_tidak_ditemukan',
                'pesan' => 'Kode peminjaman tidak ditemukan.',
            ], 404);
        }

        // Konversi bentuk request (lines) ke bentuk Service (baris).
        $baris = $this->konversiLines($request->validated('lines'));

        $klaim = $this->klaim->buat([
            'kode_pinjam' => $kode,
            'idempotency_key' => $request->validated('idempotency_key'),
            'catatan_peminjam' => $request->validated('catatan'),
            'baris' => $baris,
        ]);

        $pembatas->catatSukses($ip);

        $wasRecentlyCreated = $klaim->wasRecentlyCreated;
        $klaim->refresh();
        $klaim->load('lines.loanItem.item', 'loan');

        return response()->json([
            'data' => $this->respons($klaim),
        ], $wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Konversi bentuk request [{item_id, kondisi, qty}] ke bentuk Service [{item_id, baik, rusak, hilang}].
     * Gabungkan baris dengan item_id yang sama.
     */
    private function konversiLines(array $lines): array
    {
        $perItem = [];

        foreach ($lines as $line) {
            $id = (int) $line['item_id'];

            if (! isset($perItem[$id])) {
                $perItem[$id] = ['item_id' => $id, 'baik' => 0, 'rusak' => 0, 'hilang' => 0];
            }

            $perItem[$id][$line['kondisi']] += (int) $line['qty'];
        }

        return array_values($perItem);
    }

    private function respons(ReturnClaim $klaim): array
    {
        $lines = $klaim->lines->map(fn ($line) => [
            'nama' => $line->loanItem?->item?->nama ?? 'barang',
            'kondisi_dilaporkan' => $line->kondisi_dilaporkan,
            'qty' => (int) $line->qty,
        ])->values()->all();

        return [
            'kode_klaim' => $klaim->kode_klaim,
            'kode_pinjam' => $klaim->loan->kode_pinjam,
            'status' => $klaim->status,
            'diklaim_pada' => $klaim->diklaim_pada?->toIso8601String(),
            'hari_terlambat' => (int) $klaim->hari_terlambat,
            'catatan_peminjam' => $klaim->catatan_peminjam,
            'lines' => $lines,
        ];
    }
}
