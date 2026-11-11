<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PerpanjanganAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tambahan_hari' => ['required', 'integer', 'min:1'],
            'alasan' => ['required', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'tambahan_hari.required' => 'Tambahan hari wajib diisi.',
            'tambahan_hari.min' => 'Tambahan hari minimal 1.',
            'alasan.required' => 'Alasan wajib diisi.',
            'alasan.max' => 'Alasan maksimal 500 karakter.',
        ];
    }
}
