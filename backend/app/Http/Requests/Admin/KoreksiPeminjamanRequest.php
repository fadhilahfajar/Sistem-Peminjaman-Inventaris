<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class KoreksiPeminjamanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nama_peminjam' => ['sometimes', 'string', 'min:2', 'max:100'],
            'division_id' => ['sometimes', 'integer'],
            'telepon' => ['sometimes', 'nullable', 'string', 'max:20'],
            'tanggal_pinjam' => ['sometimes', 'date_format:Y-m-d'],
            'batas_kembali' => ['sometimes', 'date_format:Y-m-d'],
            'alasan' => ['required', 'string', 'max:500'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'alasan.required' => 'Alasan wajib diisi.',
            'alasan.max' => 'Alasan maksimal 500 karakter.',
            'items.*.qty.min' => 'Jumlah pinjam minimal 1.',
        ];
    }
}
