<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Baca-saja jejak audit (FR-A-13).
 */
class AuditAdminService
{
    /**
     * @param  array<string, mixed>  $filter
     */
    public function daftar(array $filter, int $pageSize = 25): LengthAwarePaginator
    {
        $pageSize = max(1, min($pageSize, 100));

        return AuditLog::query()
            ->with('user')
            ->when($filter['aksi'] ?? null, fn ($query, $nilai) => $query->where('aksi', $nilai))
            ->when($filter['entitas'] ?? null, fn ($query, $nilai) => $query->where('entitas', $nilai))
            ->when($filter['user_id'] ?? null, fn ($query, $nilai) => $query->where('user_id', (int) $nilai))
            ->orderByDesc('dibuat_pada')
            ->orderByDesc('id')
            ->paginate($pageSize);
    }
}
