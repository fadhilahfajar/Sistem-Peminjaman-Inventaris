<?php

namespace App\Http\Requests\Admin;

use App\Models\Item;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UbahBarangRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Item|string|int|null $item */
        $item = $this->route('item');
        $itemId = $item instanceof Item ? $item->id : (int) $item;

        return [
            'nama' => [
                'required',
                'string',
                'min:2',
                'max:150',
                Rule::unique('items', 'nama')
                    ->whereNull('dihapus_pada')
                    ->ignore($itemId),
            ],
            'deskripsi' => ['nullable', 'string'],
            'kategori_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->where('aktif', true),
            ],
            'boleh_diperpanjang' => ['required', 'boolean'],
            'total' => ['prohibited'],
            'tersedia' => ['prohibited'],
            'rusak' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'total.prohibited' => 'Gunakan penyesuaian stok.',
            'tersedia.prohibited' => 'Gunakan penyesuaian stok.',
            'rusak.prohibited' => 'Gunakan penyesuaian stok.',
            'kategori_id.exists' => 'Kategori yang dipilih tidak valid atau tidak aktif.',
        ];
    }
}
