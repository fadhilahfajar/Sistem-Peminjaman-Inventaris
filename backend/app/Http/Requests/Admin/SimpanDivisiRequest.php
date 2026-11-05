<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SimpanDivisiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'min:2', 'max:100', 'unique:divisions,nama'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama.required' => 'Nama divisi wajib diisi.',
            'nama.min' => 'Nama divisi minimal 2 karakter.',
            'nama.max' => 'Nama divisi maksimal 100 karakter.',
            'nama.unique' => 'Nama divisi sudah dipakai.',
        ];
    }
}
