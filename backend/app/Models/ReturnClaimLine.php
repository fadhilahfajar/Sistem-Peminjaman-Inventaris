<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnClaimLine extends ModelDomain
{
    protected $fillable = [
        'claim_id', 'loan_item_id', 'kondisi_dilaporkan', 'qty',
        'qty_baik', 'qty_rusak', 'qty_hilang', 'qty_ditolak',
    ];

    protected $casts = [
        'qty' => 'integer',
        'qty_baik' => 'integer',
        'qty_rusak' => 'integer',
        'qty_hilang' => 'integer',
        'qty_ditolak' => 'integer',
    ];

    public function claim(): BelongsTo
    {
        return $this->belongsTo(ReturnClaim::class, 'claim_id');
    }

    public function loanItem(): BelongsTo
    {
        return $this->belongsTo(LoanItem::class);
    }
}
