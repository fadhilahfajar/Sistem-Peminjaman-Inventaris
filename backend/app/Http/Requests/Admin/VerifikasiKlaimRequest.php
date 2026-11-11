<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class VerifikasiKlaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.line_id' => ['required', 'integer', 'min:1'],
            'lines.*.qty_baik' => ['required', 'integer', 'min:0'],
            'lines.*.qty_rusak' => ['required', 'integer', 'min:0'],
            'lines.*.qty_hilang' => ['required', 'integer', 'min:0'],
            'lines.*.qty_ditolak' => ['required', 'integer', 'min:0'],
            'catatan_admin' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'lines.required' => 'Hasil verifikasi wajib diisi.',
            'lines.*.qty_baik.min' => 'Jumlah harus bilangan bulat 0 atau lebih.',
            'lines.*.qty_rusak.min' => 'Jumlah harus bilangan bulat 0 atau lebih.',
            'lines.*.qty_hilang.min' => 'Jumlah harus bilangan bulat 0 atau lebih.',
            'lines.*.qty_ditolak.min' => 'Jumlah harus bilangan bulat 0 atau lebih.',
            'catatan_admin.max' => 'Catatan admin maksimal 500 karakter.',
        ];
    }
}
