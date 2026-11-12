<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Satu klaim pengembalian untuk antrean dan detail admin (FR-A-04). */
class AdminReturnClaimResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kode_klaim' => $this->kode_klaim,
            'status' => $this->status,
            'hari_terlambat' => (int) $this->hari_terlambat,
            'catatan_peminjam' => $this->catatan_peminjam,
            'catatan_admin' => $this->catatan_admin,
            'diklaim_pada' => $this->diklaim_pada?->toISOString(),
            'diverifikasi_pada' => $this->diverifikasi_pada?->toISOString(),
            'verifikator' => $this->verifikator ? [
                'id' => $this->verifikator->id,
                'nama' => $this->verifikator->name,
            ] : null,
            'peminjaman' => [
                'id' => $this->loan->id,
                'kode_pinjam' => $this->loan->kode_pinjam,
                'nama_peminjam' => $this->loan->nama_peminjam,
                'telepon' => $this->loan->telepon,
                'divisi' => [
                    'id' => $this->loan->divisi->id,
                    'nama' => $this->loan->divisi->nama,
                ],
                'tanggal_pinjam' => $this->loan->tanggal_pinjam->format('Y-m-d'),
                'batas_kembali' => $this->loan->batas_kembali->format('Y-m-d'),
            ],
            'jumlah_unit' => (int) $this->lines->sum('qty'),
            'lines' => $this->lines->sortBy('id')->values()->map(fn ($baris) => [
                'line_id' => $baris->id,
                'item_id' => $baris->loanItem?->item_id,
                'nama' => $baris->loanItem?->item?->nama,
                'kondisi_dilaporkan' => $baris->kondisi_dilaporkan,
                'qty' => (int) $baris->qty,
                'hasil' => $baris->qty_baik === null ? null : [
                    'qty_baik' => (int) $baris->qty_baik,
                    'qty_rusak' => (int) $baris->qty_rusak,
                    'qty_hilang' => (int) $baris->qty_hilang,
                    'qty_ditolak' => (int) $baris->qty_ditolak,
                ],
            ])->all(),
        ];
    }
}
