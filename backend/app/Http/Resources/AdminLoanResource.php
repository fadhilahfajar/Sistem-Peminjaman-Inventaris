<?php

namespace App\Http\Resources;

use App\Services\StatusPeminjaman;
use App\Support\Tanggal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Satu baris daftar peminjaman admin (FR-A-05). Bentuk detail di AdminLoanDetailResource. */
class AdminLoanResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $status = app(StatusPeminjaman::class)->dariModel($this->resource, Tanggal::hariIni());

        return [
            'id' => $this->id,
            'kode_pinjam' => $this->kode_pinjam,
            'nama_peminjam' => $this->nama_peminjam,
            'divisi' => [
                'id' => $this->divisi->id,
                'nama' => $this->divisi->nama,
            ],
            'tipe_peminjam' => $this->tipe_peminjam,
            'telepon' => $this->telepon,
            'tanggal_pinjam' => $this->tanggal_pinjam->format('Y-m-d'),
            'batas_kembali' => $this->batas_kembali->format('Y-m-d'),
            'status' => $status['status'],
            'hari_terlambat' => $status['hari_terlambat'],
            'o' => $status['o'],
            'p' => $status['p'],
            'lencana' => $status['lencana'],
            'diperpanjang_kali' => $status['diperpanjang_kali'],
            'jumlah_unit' => (int) $this->items->sum('qty_dipinjam'),
            'items' => $this->items->map(fn ($baris) => [
                'item_id' => $baris->item_id,
                'nama' => $baris->item?->nama,
                'qty_dipinjam' => (int) $baris->qty_dipinjam,
            ])->values()->all(),
            'dibuat_pada' => $this->dibuat_pada?->toISOString(),
            'dibatalkan_pada' => $this->dibatalkan_pada?->toISOString(),
        ];
    }
}
