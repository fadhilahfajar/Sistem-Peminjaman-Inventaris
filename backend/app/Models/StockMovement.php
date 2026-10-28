<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends ModelDomain
{
    protected $fillable = [
        'item_id', 'jenis', 'delta_total', 'delta_tersedia', 'delta_rusak',
        'ref_tipe', 'ref_id', 'user_id', 'catatan',
    ];

    protected $casts = [
        'delta_total' => 'integer',
        'delta_tersedia' => 'integer',
        'delta_rusak' => 'integer',
        'dibuat_pada' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
