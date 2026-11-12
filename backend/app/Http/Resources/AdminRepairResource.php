<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminRepairResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nama' => $this->nama,
            'kategori' => $this->kategori ? [
                'id' => $this->kategori->id,
                'nama' => $this->kategori->nama,
            ] : null,
            'rusak' => (int) $this->rusak,
            'tersedia' => (int) $this->tersedia,
            'total' => (int) $this->total,
        ];
    }
}
