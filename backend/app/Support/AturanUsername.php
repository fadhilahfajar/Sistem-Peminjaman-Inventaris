<?php

namespace App\Support;

class AturanUsername
{
    /** Pola username setelah diubah ke huruf kecil. */
    public const POLA = '/^[a-z0-9][a-z0-9._-]*$/';

    /**
     * Aturan validasi username (input akan dinormalisasi ke huruf kecil oleh pemanggil).
     *
     * @return array<int, mixed>
     */
    public static function rules(bool $wajib = true): array
    {
        $aturan = [$wajib ? 'required' : 'nullable', 'string', 'min:3', 'max:50', 'regex:'.self::POLA];

        if (! $wajib) {
            array_unshift($aturan, 'sometimes');
        }

        return $aturan;
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'username.required' => 'Username wajib diisi.',
            'username.string' => 'Username harus berupa teks.',
            'username.min' => 'Username minimal 3 karakter.',
            'username.max' => 'Username maksimal 50 karakter.',
            'username.regex' => 'Username harus diawali huruf kecil/angka dan hanya boleh berisi huruf kecil, angka, titik, garis bawah, atau tanda hubung.',
            'username.unique' => 'Username sudah dipakai.',
        ];
    }
}
