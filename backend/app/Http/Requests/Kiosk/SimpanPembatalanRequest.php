<?php

namespace App\Http\Requests\Kiosk;

use Illuminate\Foundation\Http\FormRequest;

class SimpanPembatalanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'kode_pinjam' => ['required', 'string'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'kode_pinjam.required' => 'Kode peminjaman wajib diisi.',
            'kode_pinjam.string' => 'Kode peminjaman harus berupa string.',
        ];
    }
}
