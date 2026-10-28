<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Dasar semua model domain: tanpa created_at/updated_at (DESAIN 6). */
abstract class ModelDomain extends Model
{
    public $timestamps = false;
}
