<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SimpanBarangRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nama' => [
                'required',
                'string',
                'min:2',
                'max:150',
                Rule::unique('items', 'nama')->whereNull('dihapus_pada'),
            ],
            'deskripsi' => ['nullable', 'string'],
            'kategori_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->where('aktif', true),
            ],
            'boleh_diperpanjang' => ['sometimes', 'boolean'],
            'jumlah_awal' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'total' => ['prohibited'],
            'tersedia' => ['prohibited'],
            'rusak' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'total.prohibited' => 'Gunakan penyesuaian stok.',
            'tersedia.prohibited' => 'Gunakan penyesuaian stok.',
            'rusak.prohibited' => 'Gunakan penyesuaian stok.',
            'kategori_id.exists' => 'Kategori yang dipilih tidak valid atau tidak aktif.',
        ];
    }
}
