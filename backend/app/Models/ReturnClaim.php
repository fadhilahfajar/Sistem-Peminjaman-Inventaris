<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReturnClaim extends ModelDomain
{
    protected $fillable = [
        'kode_klaim', 'loan_id', 'catatan_peminjam', 'hari_terlambat', 'status',
        'diverifikasi_oleh', 'diverifikasi_pada', 'catatan_admin', 'idempotency_key',
    ];

    protected $casts = [
        'hari_terlambat' => 'integer',
        'diklaim_pada' => 'datetime',
        'diverifikasi_pada' => 'datetime',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ReturnClaimLine::class, 'claim_id');
    }

    public function verifikator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }
}
