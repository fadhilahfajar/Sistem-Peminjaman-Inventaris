<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanItem extends ModelDomain
{
    protected $fillable = ['loan_id', 'item_id', 'qty_dipinjam'];

    protected $casts = ['qty_dipinjam' => 'integer'];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    /** withTrashed agar riwayat tetap menampilkan barang yang sudah di-soft-delete. */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class)->withTrashed();
    }

    public function claimLines(): HasMany
    {
        return $this->hasMany(ReturnClaimLine::class, 'loan_item_id');
    }
}
