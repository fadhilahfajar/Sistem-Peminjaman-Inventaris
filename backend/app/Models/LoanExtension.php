<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanExtension extends ModelDomain
{
    protected $fillable = [
        'loan_id', 'batas_lama', 'batas_baru', 'tambahan_hari', 'alasan', 'diajukan_oleh', 'user_id',
    ];

    protected $casts = [
        'batas_lama' => 'date:Y-m-d',
        'batas_baru' => 'date:Y-m-d',
        'tambahan_hari' => 'integer',
        'dibuat_pada' => 'datetime',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
