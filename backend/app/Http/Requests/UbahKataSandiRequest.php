<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UbahKataSandiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password_lama' => ['required', 'string', 'current_password:web'],
            'password_baru' => ['required', 'string', 'min:12', 'max:255', 'confirmed', 'different:password_lama'],
        ];
    }

    public function messages(): array
    {
        return [
            'password_lama.required' => 'Kata sandi lama wajib diisi.',
            'password_lama.current_password' => 'Kata sandi lama tidak sesuai.',
            'password_baru.required' => 'Kata sandi baru wajib diisi.',
            'password_baru.min' => 'Kata sandi baru minimal 12 karakter.',
            'password_baru.confirmed' => 'Konfirmasi kata sandi baru tidak sama.',
            'password_baru.different' => 'Kata sandi baru harus berbeda dari yang lama.',
        ];
    }
}
