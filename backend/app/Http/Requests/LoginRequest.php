<?php

namespace App\Http\Requests;

use App\Support\AturanUsername;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('username') && is_string($this->username)) {
            $this->merge(['username' => mb_strtolower(trim($this->username))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'username' => AturanUsername::rules(),
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return AturanUsername::messages();
    }
}
