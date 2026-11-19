<?php

namespace App\Http\Requests\Admin;

use App\Support\AturanUsername;
use Illuminate\Foundation\Http\FormRequest;

class SimpanPenggunaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];
        if ($this->has('username') && is_string($this->username)) {
            $data['username'] = mb_strtolower(trim($this->username));
        }
        if ($this->has('email') && is_string($this->email)) {
            $email = mb_strtolower(trim($this->email));
            $data['email'] = $email === '' ? null : $email;
        }
        if ($data !== []) {
            $this->merge($data);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'username' => array_merge(AturanUsername::rules(), ['unique:users,username']),
            'email' => ['nullable', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return array_merge(AturanUsername::messages(), [
            'name.required' => 'Nama wajib diisi.',
            'name.min' => 'Nama minimal 2 karakter.',
            'email.email' => 'Email tidak valid.',
            'email.unique' => 'Email sudah dipakai.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.min' => 'Kata sandi minimal 12 karakter.',
        ]);
    }
}
