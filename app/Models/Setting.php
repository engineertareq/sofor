<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One platform wide value, keyed by name. Read through BrandingService. */
class Setting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value'];
}
