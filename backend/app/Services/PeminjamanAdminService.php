<?php

namespace App\Services;

use App\Models\Loan;
use App\Support\Tanggal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Baca-saja daftar dan detail peminjaman untuk panel admin (FR-A-05, FR-A-06).
 * Seluruh perubahan (koreksi, perpanjangan, pembatalan) tetap memakai Service B.
 */
class PeminjamanAdminService
{
    /** Status turunan yang boleh difilter (DESAIN 4). */
    public const STATUS = [
        'dipesan', 'dipinjam', 'terlambat', 'menunggu_verifikasi', 'selesai', 'dibatalkan',
    ];

    /**
     * @param  array<string, mixed>  $filter
     */
    public function daftar(array $filter, int $pageSize = 25): LengthAwarePaginator
    {
        $pageSize = max(1, min($pageSize, 100));
        $hariIni = Tanggal::hariIni();

        // O = unit masih di tangan peminjam, P = unit menunggu verifikasi (DESAIN 4).
        $menunggu = "(SELECT COALESCE(SUM(rcl.qty), 0) FROM return_claim_lines rcl
            JOIN return_claims rc ON rc.id = rcl.claim_id
            JOIN loan_items li ON li.id = rcl.loan_item_id
            WHERE li.loan_id = loans.id AND rc.status = 'menunggu')";
        $terverifikasi = "(SELECT COALESCE(SUM(rcl.qty_baik + rcl.qty_rusak + rcl.qty_hilang), 0)
            FROM return_claim_lines rcl
            JOIN return_claims rc ON rc.id = rcl.claim_id
            JOIN loan_items li ON li.id = rcl.loan_item_id
            WHERE li.loan_id = loans.id AND rc.status = 'diverifikasi')";
        $total = '(SELECT COALESCE(SUM(li.qty_dipinjam), 0) FROM loan_items li WHERE li.loan_id = loans.id)';
        $o = "({$total} - {$menunggu} - {$terverifikasi})";

        $query = Loan::query()
            ->with(['divisi', 'items.item', 'items.claimLines.claim', 'claims', 'extensions'])
            ->when($filter['status'] ?? null, function ($query, string $status) use ($o, $menunggu, $hariIni) {
                [$sql, $bindings] = $this->sqlStatus($status, $o, $menunggu, $hariIni);
                $query->whereRaw($sql, $bindings);
            })
            ->when($filter['barang'] ?? null, fn ($query, $id) => $query->whereHas(
                'items',
                fn ($q) => $q->where('item_id', (int) $id),
            ))
            ->when($filter['kategori'] ?? null, fn ($query, $id) => $query->whereHas(
                'items.item',
                fn ($q) => $q->where('kategori_id', (int) $id),
            ))
            ->when($filter['divisi'] ?? null, fn ($query, $id) => $query->where('division_id', (int) $id))
            ->when($filter['tipe_peminjam'] ?? null, fn ($query, $tipe) => $query->where('tipe_peminjam', $tipe))
            ->when($filter['dari'] ?? null, fn ($query, $tgl) => $query->where('tanggal_pinjam', '>=', $tgl))
            ->when($filter['sampai'] ?? null, fn ($query, $tgl) => $query->where('tanggal_pinjam', '<=', $tgl))
            ->when($filter['q'] ?? null, function ($query, string $q) {
                $cari = '%'.addcslashes(trim($q), '%_\\').'%';
                $query->where(function ($q) use ($cari) {
                    $q->where('nama_peminjam', 'like', $cari)
                        ->orWhere('kode_pinjam', 'like', $cari)
                        ->orWhere('telepon', 'like', $cari);
                });
            })
            ->when($this->benar($filter['ada_catatan'] ?? null), fn ($query) => $query->whereExists(
                fn ($sub) => $sub->selectRaw('1')->from('return_claims')
                    ->whereColumn('return_claims.loan_id', 'loans.id')
                    ->where(fn ($w) => $w
                        ->whereRaw("TRIM(COALESCE(return_claims.catatan_peminjam, '')) <> ''")
                        ->orWhereRaw("TRIM(COALESCE(return_claims.catatan_admin, '')) <> ''")),
            ))
            ->when($this->benar($filter['ada_kerusakan'] ?? null), fn ($query) => $query->whereExists(
                fn ($sub) => $sub->selectRaw('1')
                    ->from('return_claim_lines')
                    ->join('return_claims', 'return_claims.id', '=', 'return_claim_lines.claim_id')
                    ->whereColumn('return_claims.loan_id', 'loans.id')
                    ->where(fn ($w) => $w
                        ->whereIn('return_claim_lines.kondisi_dilaporkan', ['rusak', 'hilang'])
                        ->orWhere('return_claim_lines.qty_rusak', '>', 0)
                        ->orWhere('return_claim_lines.qty_hilang', '>', 0)),
            ))
            ->when($this->benar($filter['belum_kembali'] ?? null), fn ($query) => $query->whereRaw(
                "({$o} + {$menunggu}) > 0",
            ))
            ->orderByDesc('dibuat_pada')
            ->orderByDesc('id');

        return $query->paginate($pageSize);
    }

    public function detail(Loan $loan): Loan
    {
        return $loan->load([
            'divisi', 'pembatal', 'items.item', 'items.claimLines.claim',
            'claims.lines.loanItem.item', 'claims.verifikator',
            'extensions.user', 'corrections.user',
        ]);
    }

    /**
     * @return array{0: string, 1: list<string>}
     */
    private function sqlStatus(string $status, string $o, string $menunggu, string $hariIni): array
    {
        $aktif = 'loans.dibatalkan_pada IS NULL';

        return match ($status) {
            'dibatalkan' => ['loans.dibatalkan_pada IS NOT NULL', []],
            'dipesan' => ["{$aktif} AND loans.tanggal_pinjam > ?", [$hariIni]],
            'selesai' => ["{$aktif} AND loans.tanggal_pinjam <= ? AND {$o} = 0 AND {$menunggu} = 0", [$hariIni]],
            'menunggu_verifikasi' => ["{$aktif} AND loans.tanggal_pinjam <= ? AND {$o} = 0 AND {$menunggu} > 0", [$hariIni]],
            'terlambat' => ["{$aktif} AND loans.tanggal_pinjam <= ? AND {$o} > 0 AND loans.batas_kembali < ?", [$hariIni, $hariIni]],
            default => ["{$aktif} AND loans.tanggal_pinjam <= ? AND {$o} > 0 AND loans.batas_kembali >= ?", [$hariIni, $hariIni]],
        };
    }

    private function benar(mixed $nilai): bool
    {
        return filter_var($nilai, FILTER_VALIDATE_BOOLEAN);
    }
}
