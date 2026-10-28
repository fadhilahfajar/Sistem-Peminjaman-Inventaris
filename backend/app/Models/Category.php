<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends ModelDomain
{
    protected $fillable = ['nama', 'aktif'];

    protected $casts = ['aktif' => 'boolean'];

    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'kategori_id');
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('aktif', 1);
    }
}
