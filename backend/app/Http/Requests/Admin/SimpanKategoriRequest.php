<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SimpanKategoriRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'min:2', 'max:100', 'unique:categories,nama'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama.required' => 'Nama kategori wajib diisi.',
            'nama.min' => 'Nama kategori minimal 2 karakter.',
            'nama.max' => 'Nama kategori maksimal 100 karakter.',
            'nama.unique' => 'Nama kategori sudah dipakai.',
        ];
    }
}
