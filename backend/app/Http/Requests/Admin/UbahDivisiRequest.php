<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UbahDivisiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Divisi yang diubah tersedia sebagai param rute {divisi}.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'nama' => [
                'sometimes',
                'string',
                'min:2',
                'max:100',
                Rule::unique('divisions', 'nama')->ignore($this->route('divisi')->id ?? null),
            ],
            'aktif' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama.min' => 'Nama divisi minimal 2 karakter.',
            'nama.max' => 'Nama divisi maksimal 100 karakter.',
            'nama.unique' => 'Nama divisi sudah dipakai divisi lain.',
            'aktif.boolean' => 'Status aktif harus berupa true atau false.',
        ];
    }
}
