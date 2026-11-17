<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LaporanRequest;
use App\Services\LaporanService;
use App\Support\Audit;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class LaporanController extends Controller
{
    private const JENIS = ['rekap', 'belum-kembali', 'stok', 'pengembalian'];

    public function __construct(private readonly LaporanService $laporan) {}

    public function show(string $jenis, LaporanRequest $request): Response
    {
        abort_unless(in_array($jenis, self::JENIS, true), 404);

        $waktu = Carbon::now('Asia/Jakarta');
        $filter = $request->validated();

        $data = match ($jenis) {
            'rekap' => $this->laporan->rekap($filter, $request->user()->name, $waktu->format('Y-m-d H:i:s')),
            'belum-kembali' => $this->laporan->belumKembali($filter, $request->user()->name, $waktu->format('Y-m-d H:i:s')),
            'stok' => $this->laporan->stok($filter, $request->user()->name, $waktu->format('Y-m-d H:i:s')),
            'pengembalian' => $this->laporan->pengembalian($filter, $request->user()->name, $waktu->format('Y-m-d H:i:s')),
        };

        $view = $jenis === 'belum-kembali' ? 'laporan.belum_kembali' : "laporan.{$jenis}";
        $pdf = Pdf::loadView($view, $data)->output();

        Audit::catat('laporan.diunduh', 'laporan', null, $request->user()->id, [
            'jenis' => $jenis,
            'filter' => $data['filterRingkas'],
            'jumlah_baris' => $data['jumlahBaris'],
        ]);

        $respons = response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"laporan-{$jenis}-{$waktu->format('Ymd-Hi')}.pdf\"",
            'Cache-Control' => 'no-store',
        ]);

        // Symfony menambahkan "private" secara bawaan; paksa nilai persis "no-store".
        $respons->headers = new class($respons->headers->all()) extends ResponseHeaderBag
        {
            protected function computeCacheControlValue(): string
            {
                return 'no-store';
            }
        };

        return $respons;
    }
}
