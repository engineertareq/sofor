<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One install of the Sofor app that alerts are pushed to. A phone belongs to
 * whoever signed in on it last, so registering a token moves it to that account.
 */
class DeviceToken extends Model
{
    protected $fillable = [
        'user_id',
        'token',
        'token_hash',
        'platform',
        'app_version',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The token is too long for a unique index, so rows are found by its hash. */
    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function register(User $user, string $token, ?string $appVersion = null): self
    {
        return self::updateOrCreate(
            ['token_hash' => self::hash($token)],
            ['user_id' => $user->id, 'token' => $token, 'platform' => 'android', 'app_version' => $appVersion, 'last_used_at' => now()],
        );
    }

    public static function forget(string $token): void
    {
        self::where('token_hash', self::hash($token))->delete();
    }
}
