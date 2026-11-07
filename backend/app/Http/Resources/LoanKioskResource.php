<?php

namespace App\Http\Resources;

use App\Services\StatusPeminjaman;
use App\Support\Tanggal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanKioskResource extends JsonResource
{
    /**
     * Bentuk respons kiosk untuk peminjaman baru / pemesanan (DESAIN 8.1, BR-18).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $statusInfo = app(StatusPeminjaman::class)->dariModel($this->resource, Tanggal::hariIni());
        $hariIni = Tanggal::hariIni();
        $tanggalPinjam = $this->tanggal_pinjam->format('Y-m-d');

        return [
            'kode_pinjam' => $this->kode_pinjam,
            'nama_peminjam' => $this->nama_peminjam,
            'division' => [
                'id' => $this->divisi->id,
                'nama' => $this->divisi->nama,
            ],
            'tipe_peminjam' => $this->tipe_peminjam,
            'tanggal_pinjam' => $tanggalPinjam,
            'batas_kembali' => $this->batas_kembali->format('Y-m-d'),
            'status' => $statusInfo['status'],
            'pemesanan' => $tanggalPinjam > $hariIni,
            'items' => $this->items->map(fn ($item) => [
                'nama' => $item->item->nama,
                'qty_dipinjam' => (int) $item->qty_dipinjam,
            ])->values()->all(),
        ];
    }
}
