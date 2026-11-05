<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends ModelDomain
{
    use HasFactory;
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

    public function scopeKatalogKiosk(Builder $query, ?string $q = null, mixed $kategoriId = null): Builder
    {
        return $query
            ->when($q !== null && trim($q) !== '', function (Builder $query) use ($q) {
                $escaped = addcslashes(trim($q), '%_\\');
                $query->where('nama', 'like', "%{$escaped}%");
            })
            ->when($kategoriId !== null && $kategoriId !== '', function (Builder $query) use ($kategoriId) {
                $query->where('kategori_id', (int) $kategoriId);
            })
            ->orderBy('nama');
    }

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
