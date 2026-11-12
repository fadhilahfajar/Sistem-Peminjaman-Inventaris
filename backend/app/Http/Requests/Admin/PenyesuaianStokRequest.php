<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PenyesuaianStokRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'arah' => ['required', 'string', 'in:tambah,kurang'],
            'jumlah' => ['required', 'integer', 'min:1'],
            'alasan' => ['required', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'arah.in' => 'Arah penyesuaian harus berupa tambah atau kurang.',
            'jumlah.integer' => 'Jumlah harus bilangan bulat.',
            'jumlah.min' => 'Jumlah minimal 1.',
            'alasan.required' => 'Alasan wajib diisi.',
        ];
    }
}
