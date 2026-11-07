<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Menghitung ulang BR-01 dan mencocokkan items dengan jumlah delta di stock_movements (DESAIN 3.2).
 * Aturan invarian hanya didefinisikan di sini: dipakai perintah inventaris:periksa-invarian (2.12)
 * dan helper tes assertInvarian() (2.13).
 */
class PemeriksaInvarian
{
    private const SQL = <<<'SQL'
SELECT i.id, i.nama, i.total, i.tersedia, i.rusak,
  (SELECT COALESCE(SUM(li.qty_dipinjam), 0) FROM loan_items li
     JOIN loans l ON l.id = li.loan_id
     WHERE li.item_id = i.id AND l.dibatalkan_pada IS NULL) AS qty_dipinjam,
  (SELECT COALESCE(SUM(rcl.qty), 0) FROM return_claim_lines rcl
     JOIN return_claims rc ON rc.id = rcl.claim_id
     JOIN loan_items li ON li.id = rcl.loan_item_id
     JOIN loans l ON l.id = li.loan_id
     WHERE li.item_id = i.id AND rc.status = 'menunggu' AND l.dibatalkan_pada IS NULL) AS menunggu,
  (SELECT COALESCE(SUM(COALESCE(rcl.qty_baik, 0) + COALESCE(rcl.qty_rusak, 0) + COALESCE(rcl.qty_hilang, 0)), 0)
     FROM return_claim_lines rcl
     JOIN return_claims rc ON rc.id = rcl.claim_id
     JOIN loan_items li ON li.id = rcl.loan_item_id
     JOIN loans l ON l.id = li.loan_id
     WHERE li.item_id = i.id AND rc.status = 'diverifikasi' AND l.dibatalkan_pada IS NULL) AS terverifikasi,
  (SELECT COALESCE(SUM(sm.delta_total), 0) FROM stock_movements sm WHERE sm.item_id = i.id) AS buku_total,
  (SELECT COALESCE(SUM(sm.delta_tersedia), 0) FROM stock_movements sm WHERE sm.item_id = i.id) AS buku_tersedia,
  (SELECT COALESCE(SUM(sm.delta_rusak), 0) FROM stock_movements sm WHERE sm.item_id = i.id) AS buku_rusak
FROM items i
ORDER BY i.id
SQL;

    /**
     * @param  bool  $denganBuku  false hanya memeriksa BR-01. Dipakai tes yang membuat barang tanpa buku besar.
     * @return list<string> daftar pelanggaran; kosong berarti sehat
     */
    public function periksa(bool $denganBuku = true): array
    {
        $pelanggaran = [];

        foreach (DB::select(self::SQL) as $b) {
            $label = "Barang #{$b->id} '{$b->nama}'";
            $total = (int) $b->total;
            $tersedia = (int) $b->tersedia;
            $rusak = (int) $b->rusak;
            $menunggu = (int) $b->menunggu;
            $dipinjam = (int) $b->qty_dipinjam - $menunggu - (int) $b->terverifikasi;

            if ($total < 0 || $tersedia < 0 || $rusak < 0 || $dipinjam < 0 || $menunggu < 0) {
                $pelanggaran[] = "{$label}: ada angka negatif (total {$total}, tersedia {$tersedia}, rusak {$rusak}, dipinjam {$dipinjam}, menunggu {$menunggu}).";
            }

            if ($tersedia + $rusak + $dipinjam + $menunggu !== $total) {
                $pelanggaran[] = "{$label}: BR-01 dilanggar (tersedia {$tersedia} + rusak {$rusak} + dipinjam {$dipinjam} + menunggu {$menunggu} != total {$total}).";
            }

            if (! $denganBuku) {
                continue;
            }

            foreach (['total' => $total, 'tersedia' => $tersedia, 'rusak' => $rusak] as $kolom => $nilai) {
                $buku = (int) $b->{'buku_'.$kolom};

                if ($buku !== $nilai) {
                    $pelanggaran[] = "{$label}: {$kolom} {$nilai} tidak sama dengan jumlah buku besar {$buku}.";
                }
            }
        }

        return $pelanggaran;
    }
}
