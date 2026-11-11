<?php

namespace App\Services;

use App\Models\ReturnClaim;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** Baca-saja antrean dan detail klaim pengembalian untuk admin (FR-A-04). */
class KlaimAdminService
{
    /** Status klaim yang boleh difilter. */
    public const STATUS = ['menunggu', 'diverifikasi'];

    /**
     * @param  array<string, mixed>  $filter
     */
    public function daftar(array $filter, int $pageSize = 25): LengthAwarePaginator
    {
        $pageSize = max(1, min($pageSize, 100));
        // Antrean bawaan hanya klaim menunggu; riwayat terverifikasi lewat ?status=diverifikasi.
        $status = $filter['status'] ?? 'menunggu';

        $query = ReturnClaim::query()
            ->with(['loan.divisi', 'lines.loanItem.item', 'verifikator'])
            ->where('status', $status);

        // Antrean: klaim menunggu terlama di atas. Riwayat terverifikasi terbaru di atas.
        return ($status === 'diverifikasi'
            ? $query->orderByDesc('diklaim_pada')->orderByDesc('id')
            : $query->orderBy('diklaim_pada')->orderBy('id'))
            ->paginate($pageSize);
    }

    public function detail(ReturnClaim $klaim): ReturnClaim
    {
        return $klaim->load(['loan.divisi', 'lines.loanItem.item', 'verifikator']);
    }
}
