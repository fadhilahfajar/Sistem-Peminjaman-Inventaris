<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminAuditLogResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'aksi' => $this->aksi,
            'entitas' => $this->entitas,
            'entitas_id' => $this->entitas_id,
            'detail' => $this->detail,
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ] : null,
            'dibuat_pada' => $this->dibuat_pada?->toISOString(),
        ];
    }
}
