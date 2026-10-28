<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A code texted to a phone or emailed. Only its keyed hash is stored; see OneTimeCodes. */
class VerificationCode extends Model
{
    public const PURPOSE_PHONE = 'phone_verification';

    public const PURPOSE_EMAIL = 'email_verification';

    public const PURPOSE_PASSWORD_RESET = 'password_reset';

    protected $fillable = [
        'user_id',
        'purpose',
        'destination',
        'code_hash',
        'attempts',
        'expires_at',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }
}
