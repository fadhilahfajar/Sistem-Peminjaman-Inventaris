<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends ModelDomain
{
    protected $fillable = ['user_id', 'aksi', 'entitas', 'entitas_id', 'detail'];

    protected $casts = [
        'detail' => 'array',
        'dibuat_pada' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
