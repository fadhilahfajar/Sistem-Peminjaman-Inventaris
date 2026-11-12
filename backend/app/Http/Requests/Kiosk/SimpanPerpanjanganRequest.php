<?php

namespace App\Http\Requests\Kiosk;

use Illuminate\Foundation\Http\FormRequest;

class SimpanPerpanjanganRequest extends FormRequest
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
            'tambahan_hari' => ['required', 'integer', 'min:1'],
            'alasan' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'kode_pinjam.required' => 'Kode peminjaman wajib diisi.',
            'kode_pinjam.string' => 'Kode peminjaman harus berupa string.',
            'tambahan_hari.required' => 'Tambahan hari wajib diisi.',
            'tambahan_hari.integer' => 'Tambahan hari harus bilangan bulat.',
            'tambahan_hari.min' => 'Tambahan hari minimal 1 hari.',
            'alasan.string' => 'Alasan harus berupa teks.',
            'alasan.max' => 'Alasan maksimal 500 karakter.',
        ];
    }
}
