<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DaftarPeminjamanRequest;
use App\Http\Requests\Admin\LaporanRequest;
use App\Services\LaporanService;
use App\Services\PeminjamanAdminService;
use App\Services\StatusPeminjaman;
use App\Support\Audit;
use App\Support\Tanggal;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class EksporController extends Controller
{
    public function __construct(
        private readonly PeminjamanAdminService $peminjaman,
        private readonly LaporanService $laporan,
        private readonly StatusPeminjaman $status,
    ) {}

    public function peminjaman(DaftarPeminjamanRequest $request): StreamedResponse
    {
        $filter = $request->validated();
        $filter = array_diff_key($filter, array_flip(['page', 'page_size']));

        $query = $this->peminjaman->queryDaftar($filter);
        $jumlah = $query->count();

        $waktu = now('Asia/Jakarta');
        $pemisah = $this->pemisah($request);
        $ringkas = $this->laporan->ringkas($filter);

        Audit::catat('ekspor.diunduh', 'ekspor', null, $request->user()->id, [
            'jenis' => 'peminjaman',
            'filter' => $ringkas,
            'jumlah_baris' => $jumlah,
        ]);

        $namaBerkas = 'ekspor-peminjaman-'.$waktu->format('Ymd-Hi').'.csv';

        $respons = response()->streamDownload(function () use ($query, $pemisah) {
            $keluaran = fopen('php://output', 'w');
            fwrite($keluaran, "\xEF\xBB\xBF");

            fputcsv($keluaran, [
                'kode_pinjam', 'nama_peminjam', 'divisi', 'tipe_peminjam', 'tanggal_pinjam',
                'batas_kembali', 'status', 'hari_terlambat', 'jumlah_perpanjangan',
                'barang', 'ada_catatan',
            ], $pemisah, '"', '', "\n");

            foreach ($query->cursor() as $pinjam) {
                $turunan = $this->status->dariModel($pinjam, Tanggal::hariIni());
                $barang = $pinjam->items->sortBy('item_id')->map(
                    fn ($baris) => ($baris->item?->nama ?? 'Barang').' x '.(int) $baris->qty_dipinjam,
                )->implode(' | ');

                fputcsv($keluaran, [
                    $pinjam->kode_pinjam,
                    $this->aman($pinjam->nama_peminjam),
                    $this->aman($pinjam->divisi?->nama ?? '—'),
                    $pinjam->tipe_peminjam,
                    $pinjam->tanggal_pinjam->format('Y-m-d'),
                    $pinjam->batas_kembali->format('Y-m-d'),
                    $turunan['status'],
                    $turunan['hari_terlambat'],
                    $pinjam->extensions->count(),
                    $this->aman($barang),
                    in_array(StatusPeminjaman::LENCANA_CATATAN, $turunan['lencana'], true) ? 'Ya' : 'Tidak',
                ], $pemisah, '"', '', "\n");
            }

            fclose($keluaran);
        }, $namaBerkas, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);

        $respons->headers = new class($respons->headers->all()) extends ResponseHeaderBag
        {
            protected function computeCacheControlValue(): string
            {
                return 'no-store';
            }
        };

        return $respons;
    }

    public function stok(LaporanRequest $request): StreamedResponse
    {
        $filter = array_intersect_key($request->validated(), array_flip(['kategori']));

        $query = $this->laporan->queryStok($filter);
        $jumlah = $query->count();

        $waktu = now('Asia/Jakarta');
        $pemisah = $this->pemisah($request);
        $ringkas = $this->laporan->ringkas($filter + ['kategori' => $filter['kategori'] ?? null]);

        Audit::catat('ekspor.diunduh', 'ekspor', null, $request->user()->id, [
            'jenis' => 'stok',
            'filter' => $ringkas,
            'jumlah_baris' => $jumlah,
        ]);

        $namaBerkas = 'ekspor-stok-'.$waktu->format('Ymd-Hi').'.csv';

        $respons = response()->streamDownload(function () use ($query, $pemisah) {
            $keluaran = fopen('php://output', 'w');
            fwrite($keluaran, "\xEF\xBB\xBF");

            fputcsv($keluaran, [
                'kategori', 'barang', 'total', 'tersedia', 'dipinjam',
                'menunggu_verifikasi', 'rusak', 'kondisi', 'boleh_diperpanjang',
            ], $pemisah, '"', '', "\n");

            foreach ($query->cursor() as $barang) {
                $mv = (int) $barang->menunggu_verifikasi;
                $dipinjam = (int) $barang->total - (int) $barang->tersedia - (int) $barang->rusak - $mv;

                $kondisi = match (true) {
                    (int) $barang->rusak === 0 => 'Baik',
                    (int) $barang->rusak === (int) $barang->total && (int) $barang->tersedia === 0 => 'Semua rusak',
                    default => 'Sebagian rusak',
                };

                fputcsv($keluaran, [
                    $this->aman($barang->kategori_nama ?: 'Tanpa Kategori'),
                    $this->aman($barang->nama),
                    (int) $barang->total,
                    (int) $barang->tersedia,
                    $dipinjam,
                    $mv,
                    (int) $barang->rusak,
                    $kondisi,
                    $barang->boleh_diperpanjang ? 'Ya' : 'Tidak',
                ], $pemisah, '"', '', "\n");
            }

            fclose($keluaran);
        }, $namaBerkas, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);

        $respons->headers = new class($respons->headers->all()) extends ResponseHeaderBag
        {
            protected function computeCacheControlValue(): string
            {
                return 'no-store';
            }
        };

        return $respons;
    }

    private function pemisah(Request $request): string
    {
        $nilai = (string) ($request->input('pemisah') ?? $request->input('separator') ?? ';');

        return in_array($nilai, [',', 'koma', 'comma'], true) ? ',' : ';';
    }

    private function aman(string $nilai): string
    {
        if ($nilai === '') {
            return $nilai;
        }

        return in_array($nilai[0], ['=', '+', '-', '@', "\t", "\r"], true)
            ? "'".$nilai
            : $nilai;
    }
}
