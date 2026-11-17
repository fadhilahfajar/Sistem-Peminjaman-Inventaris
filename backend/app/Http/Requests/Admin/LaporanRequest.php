<?php

namespace App\Http\Requests\Admin;

class LaporanRequest extends DaftarPeminjamanRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        // Validasi bentuk sama dengan daftar peminjaman, tanpa pagination.
        return array_diff_key(parent::rules(), array_flip(['page', 'page_size']));
    }
}
