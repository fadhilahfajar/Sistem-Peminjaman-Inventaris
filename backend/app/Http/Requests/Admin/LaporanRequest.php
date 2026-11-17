<?php

namespace App\Http\Requests\Admin;

use App\Services\KlaimAdminService;
use App\Services\PeminjamanAdminService;
use Illuminate\Validation\Rule;

class LaporanRequest extends DaftarPeminjamanRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        // Validasi bentuk sama dengan daftar peminjaman, tanpa pagination.
        $aturan = array_diff_key(parent::rules(), array_flip(['page', 'page_size']));

        // Status mengikuti jenis laporan: pengembalian memakai status klaim.
        if ($this->route('jenis') === 'pengembalian') {
            $aturan['status'] = ['nullable', 'string', Rule::in(KlaimAdminService::STATUS)];
        } else {
            $aturan['status'] = ['nullable', 'string', Rule::in(PeminjamanAdminService::STATUS)];
        }

        return $aturan;
    }
}
