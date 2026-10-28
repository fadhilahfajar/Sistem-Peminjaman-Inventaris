<?php

namespace App\Models;

class Setting extends ModelDomain
{
    protected $primaryKey = 'kunci';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['kunci', 'nilai'];

    protected $casts = ['diubah_pada' => 'datetime'];
}
