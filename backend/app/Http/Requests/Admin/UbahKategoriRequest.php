<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UbahKategoriRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Kategori yang diubah tersedia sebagai param rute {kategori}.
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
                Rule::unique('categories', 'nama')->ignore($this->route('kategori')->id ?? null),
            ],
            'aktif' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama.min' => 'Nama kategori minimal 2 karakter.',
            'nama.max' => 'Nama kategori maksimal 100 karakter.',
            'nama.unique' => 'Nama kategori sudah dipakai kategori lain.',
            'aktif.boolean' => 'Status aktif harus berupa true atau false.',
        ];
    }
}
