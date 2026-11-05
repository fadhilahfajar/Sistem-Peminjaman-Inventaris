<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemKioskResource extends JsonResource
{
    /**
     * Hanya field publik katalog kiosk (DESAIN 8.1).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nama' => $this->nama,
            'tersedia' => $this->tersedia,
            'kategori_id' => $this->kategori_id,
        ];
    }
}
