<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PerbaruiPengaturanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $kunci = [
            'maks_durasi_awal_hari', 'maks_perpanjangan', 'maks_hari_per_perpanjangan',
            'maks_booking_hari', 'sesi_idle_menit', 'kiosk_idle_detik',
            'kiosk_lookup_maks_gagal', 'kiosk_lookup_kunci_menit',
        ];

        $rules = [];
        foreach ($kunci as $nama) {
            $rules[$nama] = ['sometimes', 'integer'];
        }

        return $rules;
    }
}
