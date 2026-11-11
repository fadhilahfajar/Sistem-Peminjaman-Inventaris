<?php

namespace App\Http\Resources;

use App\Services\StatusPeminjaman;
use App\Support\Tanggal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Detail peminjaman admin beserta lini masa (FR-A-06). */
class AdminLoanDetailResource extends JsonResource
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
            'batas_kembali_awal' => $this->batas_kembali_awal->format('Y-m-d'),
            'batas_kembali' => $this->batas_kembali->format('Y-m-d'),
            'status' => $status['status'],
            'hari_terlambat' => $status['hari_terlambat'],
            'o' => $status['o'],
            'p' => $status['p'],
            'lencana' => $status['lencana'],
            'diperpanjang_kali' => $status['diperpanjang_kali'],
            'jumlah_unit' => (int) $this->items->sum('qty_dipinjam'),
            'items' => $this->itemsBaris(),
            'perpanjangan' => $this->perpanjangan(),
            'klaim' => $this->klaim(),
            'koreksi' => $this->koreksi(),
            'dibatalkan_pada' => $this->dibatalkan_pada?->toISOString(),
            'alasan_batal' => $this->alasan_batal,
            'dibatalkan_oleh' => $this->pembatal ? [
                'id' => $this->pembatal->id,
                'nama' => $this->pembatal->name,
            ] : null,
            'dibuat_pada' => $this->dibuat_pada?->toISOString(),
            'lini_masa' => $this->liniMasa(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function itemsBaris(): array
    {
        return $this->items->sortBy('item_id')->values()->map(function ($baris) {
            $menunggu = 0;
            $terverifikasi = 0;

            foreach ($baris->claimLines as $lini) {
                if ($lini->claim->status === 'menunggu') {
                    $menunggu += (int) $lini->qty;
                } elseif ($lini->claim->status === 'diverifikasi') {
                    $terverifikasi += (int) $lini->qty_baik + (int) $lini->qty_rusak + (int) $lini->qty_hilang;
                }
            }

            return [
                'item_id' => $baris->item_id,
                'nama' => $baris->item?->nama,
                'qty_dipinjam' => (int) $baris->qty_dipinjam,
                'menunggu' => $menunggu,
                'terverifikasi' => $terverifikasi,
                'sisa' => (int) $baris->qty_dipinjam - $menunggu - $terverifikasi,
            ];
        })->all();
    }

    /** @return list<array<string, mixed>> */
    private function perpanjangan(): array
    {
        return $this->extensions->sortBy('dibuat_pada')->values()->map(fn ($ext) => [
            'batas_lama' => $ext->batas_lama->format('Y-m-d'),
            'batas_baru' => $ext->batas_baru->format('Y-m-d'),
            'tambahan_hari' => (int) $ext->tambahan_hari,
            'alasan' => $ext->alasan,
            'diajukan_oleh' => $ext->diajukan_oleh,
            'admin' => $ext->user ? ['id' => $ext->user->id, 'nama' => $ext->user->name] : null,
            'dibuat_pada' => $ext->dibuat_pada?->toISOString(),
        ])->all();
    }

    /** @return list<array<string, mixed>> */
    private function klaim(): array
    {
        return $this->claims->sortBy('diklaim_pada')->values()->map(fn ($klaim) => [
            'id' => $klaim->id,
            'kode_klaim' => $klaim->kode_klaim,
            'status' => $klaim->status,
            'hari_terlambat' => (int) $klaim->hari_terlambat,
            'catatan_peminjam' => $klaim->catatan_peminjam,
            'catatan_admin' => $klaim->catatan_admin,
            'diklaim_pada' => $klaim->diklaim_pada?->toISOString(),
            'diverifikasi_pada' => $klaim->diverifikasi_pada?->toISOString(),
            'verifikator' => $klaim->verifikator ? [
                'id' => $klaim->verifikator->id,
                'nama' => $klaim->verifikator->name,
            ] : null,
            'lines' => $klaim->lines->map(fn ($lini) => [
                'item_id' => $lini->loanItem?->item_id,
                'nama' => $lini->loanItem?->item?->nama,
                'kondisi_dilaporkan' => $lini->kondisi_dilaporkan,
                'qty' => (int) $lini->qty,
                'qty_baik' => $lini->qty_baik === null ? null : (int) $lini->qty_baik,
                'qty_rusak' => $lini->qty_rusak === null ? null : (int) $lini->qty_rusak,
                'qty_hilang' => $lini->qty_hilang === null ? null : (int) $lini->qty_hilang,
                'qty_ditolak' => $lini->qty_ditolak === null ? null : (int) $lini->qty_ditolak,
            ])->values()->all(),
        ])->all();
    }

    /** @return list<array<string, mixed>> */
    private function koreksi(): array
    {
        return $this->corrections->sortBy('dibuat_pada')->values()->map(fn ($koreksi) => [
            'kolom' => $koreksi->kolom,
            'nilai_lama' => $koreksi->nilai_lama,
            'nilai_baru' => $koreksi->nilai_baru,
            'alasan' => $koreksi->alasan,
            'admin' => $koreksi->user ? ['id' => $koreksi->user->id, 'nama' => $koreksi->user->name] : null,
            'dibuat_pada' => $koreksi->dibuat_pada?->toISOString(),
        ])->all();
    }

    /** Lini masa gabungan, urut waktu menaik (FR-A-06). @return list<array<string, mixed>> */
    private function liniMasa(): array
    {
        $peristiwa = collect();

        $peristiwa->push([
            'jenis' => 'dibuat',
            'waktu' => $this->dibuat_pada,
            'ringkasan' => "Peminjaman dibuat oleh {$this->nama_peminjam} ({$this->tipe_peminjam}).",
        ]);

        foreach ($this->extensions as $ext) {
            $peristiwa->push([
                'jenis' => 'perpanjangan',
                'waktu' => $ext->dibuat_pada,
                'ringkasan' => "Batas kembali {$ext->batas_lama->format('Y-m-d')} menjadi {$ext->batas_baru->format('Y-m-d')} ({$ext->tambahan_hari} hari) oleh {$ext->diajukan_oleh}.",
            ]);
        }

        foreach ($this->claims as $klaim) {
            $peristiwa->push([
                'jenis' => 'klaim',
                'waktu' => $klaim->diklaim_pada,
                'ringkasan' => "Klaim {$klaim->kode_klaim} dikirim.",
                'catatan_peminjam' => $klaim->catatan_peminjam,
            ]);

            if ($klaim->diverifikasi_pada !== null) {
                $peristiwa->push([
                    'jenis' => 'verifikasi',
                    'waktu' => $klaim->diverifikasi_pada,
                    'ringkasan' => "Klaim {$klaim->kode_klaim} diverifikasi.",
                    'catatan_admin' => $klaim->catatan_admin,
                ]);
            }
        }

        foreach ($this->corrections as $koreksi) {
            $peristiwa->push([
                'jenis' => 'koreksi',
                'waktu' => $koreksi->dibuat_pada,
                'ringkasan' => "Koreksi {$koreksi->kolom}: {$koreksi->nilai_lama} menjadi {$koreksi->nilai_baru}. Alasan: {$koreksi->alasan}",
            ]);
        }

        if ($this->dibatalkan_pada !== null) {
            $peristiwa->push([
                'jenis' => 'pembatalan',
                'waktu' => $this->dibatalkan_pada,
                'ringkasan' => 'Peminjaman dibatalkan.',
                'alasan_batal' => $this->alasan_batal,
            ]);
        }

        return $peristiwa
            ->filter(fn ($item) => $item['waktu'] !== null)
            ->sortBy(fn ($item) => $item['waktu']->getTimestamp())
            ->map(fn ($item) => ['waktu' => $item['waktu']->toISOString()] + $item)
            ->values()
            ->all();
    }
}
