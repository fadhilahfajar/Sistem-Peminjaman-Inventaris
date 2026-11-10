<?php

namespace App\Http\Requests\Kiosk;

use App\Services\PembatasKodeKiosk;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class LookupPeminjamanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Tolak sebelum menyentuh validasi atau database jika IP terkunci (K-6, T-06)
        $pembatas = app(PembatasKodeKiosk::class);
        $sisaDetik = $pembatas->periksaTerkunci((string) $this->ip());

        if ($sisaDetik !== null) {
            throw new HttpResponseException($pembatas->responseTerkunci($sisaDetik));
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'kode' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'kode.required' => 'Kode peminjaman wajib diisi.',
            'kode.string' => 'Kode peminjaman harus berupa string.',
        ];
    }
}
