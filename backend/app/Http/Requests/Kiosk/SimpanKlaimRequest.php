<?php

namespace App\Http\Requests\Kiosk;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SimpanKlaimRequest extends FormRequest
{
    use MemilikiKunciIdempotensi;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge(
            [
                'kode' => ['required', 'string'],
                'lines' => ['required', 'array', 'min:1'],
                'lines.*.item_id' => ['required', 'integer', 'min:1'],
                'lines.*.kondisi' => ['required', 'string', Rule::in(['baik', 'rusak', 'hilang'])],
                'lines.*.qty' => ['required', 'integer', 'min:1'],
                'catatan' => ['nullable', 'string', 'max:500'],
            ],
            $this->aturanIdempotensi(),
        );
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return array_merge(
            [
                'kode.required' => 'Kode peminjaman wajib diisi.',
                'kode.string' => 'Kode peminjaman harus berupa string.',
                'lines.required' => 'Isi minimal satu barang yang dikembalikan.',
                'lines.array' => 'Daftar barang tidak valid.',
                'lines.min' => 'Isi minimal satu barang yang dikembalikan.',
                'lines.*.item_id.required' => 'Barang wajib dipilih.',
                'lines.*.item_id.integer' => 'ID barang tidak valid.',
                'lines.*.item_id.min' => 'ID barang tidak valid.',
                'lines.*.kondisi.required' => 'Kondisi wajib dipilih.',
                'lines.*.kondisi.string' => 'Kondisi tidak valid.',
                'lines.*.kondisi.in' => 'Kondisi harus baik, rusak, atau hilang.',
                'lines.*.qty.required' => 'Jumlah wajib diisi.',
                'lines.*.qty.integer' => 'Jumlah harus bilangan bulat minimal 1.',
                'lines.*.qty.min' => 'Jumlah harus bilangan bulat minimal 1.',
                'catatan.string' => 'Catatan harus berupa teks.',
                'catatan.max' => 'Catatan maksimal 500 karakter.',
            ],
            $this->pesanIdempotensi(),
        );
    }
}
