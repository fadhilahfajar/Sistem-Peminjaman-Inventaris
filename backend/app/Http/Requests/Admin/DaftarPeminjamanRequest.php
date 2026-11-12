<?php

namespace App\Http\Requests\Admin;

use App\Services\PeminjamanAdminService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DaftarPeminjamanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in(PeminjamanAdminService::STATUS)],
            'barang' => ['nullable', 'integer'],
            'kategori' => ['nullable', 'integer'],
            'divisi' => ['nullable', 'integer'],
            'tipe_peminjam' => ['nullable', 'string', Rule::in(['karyawan', 'magang'])],
            'dari' => ['nullable', 'date_format:Y-m-d'],
            'sampai' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:dari'],
            'q' => ['nullable', 'string', 'max:100'],
            'ada_catatan' => ['nullable', 'boolean'],
            'ada_kerusakan' => ['nullable', 'boolean'],
            'belum_kembali' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'page_size' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
