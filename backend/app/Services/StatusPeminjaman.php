<?php

namespace App\Services;

use App\Models\Loan;
use App\Support\Tanggal;
use LogicException;

/**
 * Status peminjaman diturunkan, tidak disimpan (DESAIN 4, BR-04, BR-10).
 * O = unit di tangan peminjam (belum diklaim, termasuk yang klaimnya ditolak).
 * P = unit menunggu verifikasi.
 */
class StatusPeminjaman
{
    public const DIBATALKAN = 'dibatalkan';

    public const DIPESAN = 'dipesan';

    public const SELESAI = 'selesai';

    public const MENUNGGU_VERIFIKASI = 'menunggu_verifikasi';

    public const TERLAMBAT = 'terlambat';

    public const DIPINJAM = 'dipinjam';

    public const LENCANA_SEBAGIAN = 'sebagian';

    public const LENCANA_KLAIM_MENUNGGU = 'ada_klaim_menunggu';

    public const LENCANA_DIPERPANJANG = 'diperpanjang';

    public const LENCANA_CATATAN = 'ada_catatan';

    /**
     * @param  array{
     *     dibatalkan: bool,
     *     tanggal_pinjam: string,
     *     batas_kembali: string,
     *     jumlah_perpanjangan?: int,
     *     ada_catatan?: bool,
     *     items: list<array{qty_dipinjam: int, menunggu: int, terverifikasi: int}>
     * }  $data  tanggal berformat Y-m-d
     * @return array{status: string, o: int, p: int, hari_terlambat: int, lencana: list<string>, diperpanjang_kali: int}
     */
    public function hitung(array $data, string $hariIni): array
    {
        $o = 0;
        $p = 0;
        $diklaimAtauTerverifikasi = 0;

        foreach ($data['items'] as $item) {
            $sisa = $item['qty_dipinjam'] - $item['menunggu'] - $item['terverifikasi'];

            if ($sisa < 0) {
                throw new LogicException('Data tidak konsisten: unit diklaim atau terverifikasi melebihi jumlah dipinjam.');
            }

            $o += $sisa;
            $p += $item['menunggu'];
            $diklaimAtauTerverifikasi += $item['menunggu'] + $item['terverifikasi'];
        }

        $terlambatHari = $o > 0
            ? max(0, $this->selisihHari($data['batas_kembali'], $hariIni))
            : 0;

        $status = match (true) {
            $data['dibatalkan'] => self::DIBATALKAN,
            $data['tanggal_pinjam'] > $hariIni => self::DIPESAN,
            $o === 0 && $p === 0 => self::SELESAI,
            $o === 0 => self::MENUNGGU_VERIFIKASI,
            $hariIni > $data['batas_kembali'] => self::TERLAMBAT,
            default => self::DIPINJAM,
        };

        $diperpanjang = (int) ($data['jumlah_perpanjangan'] ?? 0);

        $lencana = [];
        if ($diklaimAtauTerverifikasi > 0 && $o > 0) {
            $lencana[] = self::LENCANA_SEBAGIAN;
        }
        if ($p > 0) {
            $lencana[] = self::LENCANA_KLAIM_MENUNGGU;
        }
        if ($diperpanjang > 0) {
            $lencana[] = self::LENCANA_DIPERPANJANG;
        }
        if (! empty($data['ada_catatan'])) {
            $lencana[] = self::LENCANA_CATATAN;
        }

        return [
            'status' => $status,
            'o' => $o,
            'p' => $p,
            'hari_terlambat' => $terlambatHari,
            'lencana' => $lencana,
            'diperpanjang_kali' => $diperpanjang,
        ];
    }

    /** Membangun data dari model. Memuat relasi yang belum dimuat. */
    public function dariModel(Loan $loan, string $hariIni): array
    {
        $loan->loadMissing('items.claimLines.claim', 'claims', 'extensions');

        $items = [];
        foreach ($loan->items as $loanItem) {
            $menunggu = 0;
            $terverifikasi = 0;

            foreach ($loanItem->claimLines as $baris) {
                if ($baris->claim->status === 'menunggu') {
                    $menunggu += $baris->qty;
                } elseif ($baris->claim->status === 'diverifikasi') {
                    // Unit yang ditolak tidak dihitung: kembali menjadi sisa (BR-04).
                    $terverifikasi += (int) $baris->qty_baik + (int) $baris->qty_rusak + (int) $baris->qty_hilang;
                }
            }

            $items[] = [
                'qty_dipinjam' => $loanItem->qty_dipinjam,
                'menunggu' => $menunggu,
                'terverifikasi' => $terverifikasi,
            ];
        }

        $adaCatatan = $loan->claims->contains(
            fn ($klaim) => trim((string) $klaim->catatan_peminjam) !== '' || trim((string) $klaim->catatan_admin) !== ''
        );

        return $this->hitung([
            'dibatalkan' => $loan->dibatalkan_pada !== null,
            'tanggal_pinjam' => $loan->tanggal_pinjam->format('Y-m-d'),
            'batas_kembali' => $loan->batas_kembali->format('Y-m-d'),
            'jumlah_perpanjangan' => $loan->extensions->count(),
            'ada_catatan' => $adaCatatan,
            'items' => $items,
        ], $hariIni);
    }

    /** Satu-satunya titik yang bergantung pada Support\Tanggal. ASUMSI: selisihHari($dari, $sampai) = $sampai - $dari. */
    private function selisihHari(string $dari, string $sampai): int
    {
        return Tanggal::selisihHari($dari, $sampai);
    }
}
