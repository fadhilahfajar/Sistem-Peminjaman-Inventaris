<?php

namespace App\Http\Requests\Kiosk;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SimpanPeminjamanRequest extends FormRequest
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
                'nama_peminjam' => ['required', 'string', 'min:2', 'max:100'],
                'division_id' => [
                    'required',
                    'integer',
                    Rule::exists('divisions', 'id')->where('aktif', 1),
                ],
                'tipe_peminjam' => ['required', 'string', Rule::in(['karyawan', 'magang'])],
                'telepon' => ['nullable', 'string', 'regex:/^[0-9]{8,15}$/'],
                'tanggal_pinjam' => ['required', 'date_format:Y-m-d'],
                'batas_kembali' => ['required', 'date_format:Y-m-d'],
                'items' => ['required', 'array', 'min:1'],
                'items.*.item_id' => [
                    'required',
                    'integer',
                    'distinct',
                    Rule::exists('items', 'id')->whereNull('dihapus_pada'),
                ],
                'items.*.qty' => ['required', 'integer', 'min:1'],
            ],
            $this->aturanIdempotensi(),
        );
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return array_merge(
            [
                'nama_peminjam.required' => 'Nama peminjam wajib diisi.',
                'nama_peminjam.string' => 'Nama peminjam harus berupa teks.',
                'nama_peminjam.min' => 'Nama peminjam minimal 2 karakter.',
                'nama_peminjam.max' => 'Nama peminjam maksimal 100 karakter.',
                'division_id.required' => 'Divisi wajib dipilih.',
                'division_id.integer' => 'Divisi tidak valid.',
                'division_id.exists' => 'Divisi tidak ditemukan atau tidak aktif.',
                'tipe_peminjam.required' => 'Tipe peminjam wajib dipilih.',
                'tipe_peminjam.string' => 'Tipe peminjam tidak valid.',
                'tipe_peminjam.in' => 'Tipe peminjam harus karyawan atau magang.',
                'telepon.string' => 'Nomor telepon harus berupa teks.',
                'telepon.regex' => 'Telepon harus 8 sampai 15 digit.',
                'tanggal_pinjam.required' => 'Tanggal pinjam wajib diisi.',
                'tanggal_pinjam.date_format' => 'Format tanggal pinjam harus YYYY-MM-DD.',
                'batas_kembali.required' => 'Batas kembali wajib diisi.',
                'batas_kembali.date_format' => 'Format batas kembali harus YYYY-MM-DD.',
                'items.required' => 'Pilih minimal satu barang.',
                'items.array' => 'Daftar barang tidak valid.',
                'items.min' => 'Pilih minimal satu barang.',
                'items.*.item_id.required' => 'Barang wajib dipilih.',
                'items.*.item_id.integer' => 'ID barang tidak valid.',
                'items.*.item_id.distinct' => 'Barang yang sama tidak boleh muncul dua kali.',
                'items.*.item_id.exists' => 'Barang tidak ditemukan atau sudah dihapus.',
                'items.*.qty.required' => 'Jumlah barang wajib diisi.',
                'items.*.qty.integer' => 'Jumlah harus bilangan bulat minimal 1.',
                'items.*.qty.min' => 'Jumlah harus bilangan bulat minimal 1.',
            ],
            $this->pesanIdempotensi(),
        );
    }
}
