<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanCorrection extends ModelDomain
{
    protected $fillable = ['loan_id', 'kolom', 'nilai_lama', 'nilai_baru', 'alasan', 'user_id'];

    protected $casts = ['dibuat_pada' => 'datetime'];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
