<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Loan extends ModelDomain
{
    protected $fillable = [
        'kode_pinjam', 'nama_peminjam', 'division_id', 'tipe_peminjam', 'telepon',
        'tanggal_pinjam', 'batas_kembali_awal', 'batas_kembali', 'idempotency_key',
        'dibatalkan_pada', 'alasan_batal', 'dibatalkan_oleh',
    ];

    /** Telepon tidak boleh ikut ke JSON secara tidak sengaja (BR-18). */
    protected $hidden = ['telepon'];

    protected $casts = [
        'tanggal_pinjam' => 'date:Y-m-d',
        'batas_kembali_awal' => 'date:Y-m-d',
        'batas_kembali' => 'date:Y-m-d',
        'dibuat_pada' => 'datetime',
        'dibatalkan_pada' => 'datetime',
    ];

    public function divisi(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'division_id');
    }

    public function pembatal(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibatalkan_oleh');
    }

    public function items(): HasMany
    {
        return $this->hasMany(LoanItem::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(ReturnClaim::class);
    }

    public function extensions(): HasMany
    {
        return $this->hasMany(LoanExtension::class);
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(LoanCorrection::class);
    }
}
