<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Division extends ModelDomain
{
    protected $fillable = ['nama', 'aktif'];

    protected $casts = ['aktif' => 'boolean'];

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class, 'division_id');
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('aktif', 1);
    }
}
