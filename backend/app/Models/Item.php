<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends ModelDomain
{
    use SoftDeletes;

    public const DELETED_AT = 'dihapus_pada';

    protected $fillable = [
        'nama', 'deskripsi', 'kategori_id', 'total', 'tersedia', 'rusak', 'boleh_diperpanjang',
    ];

    protected $casts = [
        'total' => 'integer',
        'tersedia' => 'integer',
        'rusak' => 'integer',
        'boleh_diperpanjang' => 'boolean',
        'dibuat_pada' => 'datetime',
        'diubah_pada' => 'datetime',
        'dihapus_pada' => 'datetime',
    ];

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'kategori_id');
    }

    public function loanItems(): HasMany
    {
        return $this->hasMany(LoanItem::class, 'item_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'item_id');
    }
}
