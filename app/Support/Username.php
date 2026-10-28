<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Usernames: the handle behind a traveller's public profile, sofor.io/@name.
 * Lowercase letters, numbers and underscores, 3 to 30 long, at least one letter.
 * Stored lowercase, so "Rifat" and "rifat" are the same name.
 */
final class Username
{
    public const MIN = 3;

    public const MAX = 30;

    public const PATTERN = '/^(?=.*[a-z])[a-z0-9_]{3,30}$/';

    /** Names somebody could pass off as Sofor itself, or that read like a page. */
    private const RESERVED = [
        'admin', 'administrator', 'sofor', 'solutor', 'support', 'help', 'helpdesk', 'official', 'team', 'staff',
        'moderator', 'mod', 'root', 'system', 'api', 'www', 'mail', 'email', 'security', 'billing', 'payment',
        'payments', 'bkash', 'login', 'logout', 'register', 'signup', 'signin', 'settings', 'profile', 'account',
        'dashboard', 'trips', 'trip', 'map', 'maps', 'about', 'terms', 'privacy', 'pass', 'join', 'notifications',
        'null', 'undefined', 'anonymous', 'user', 'users', 'traveller', 'travellers',
    ];

    /** "  @Rifat " becomes "rifat". */
    public static function normalize(?string $value): string
    {
        return Str::lower(ltrim(trim((string) $value), '@'));
    }

    public static function isReserved(string $username): bool
    {
        return in_array($username, self::RESERVED, true);
    }

    public static function isValid(string $username): bool
    {
        return preg_match(self::PATTERN, $username) === 1 && ! self::isReserved($username);
    }

    /**
     * Validation for a (normalized) username, ignoring the account that already owns it.
     *
     * @return list<mixed>
     */
    public static function rules(?User $owner = null): array
    {
        return [
            'required',
            'string',
            'min:'.self::MIN,
            'max:'.self::MAX,
            'regex:'.self::PATTERN,
            Rule::unique('users', 'username')->ignore($owner?->id),
            function (string $attribute, mixed $value, \Closure $fail) {
                if (is_string($value) && self::isReserved($value)) {
                    $fail(__('That username is reserved. Try another one.'));
                }
            },
        ];
    }

    /** @return array<string, string> messages for a field called $field */
    public static function messages(string $field = 'username'): array
    {
        return [
            "$field.required" => __('Pick a username for your public profile.'),
            "$field.min" => 'Use at least '.self::MIN.' characters.',
            "$field.max" => __('Keep it to :count characters or fewer.', ['count' => self::MAX]),
            "$field.regex" => __('Use lowercase letters, numbers and underscores only, with at least one letter.'),
            "$field.unique" => __('That username is taken. Try another one.'),
        ];
    }

    /** A free username built from a name (or email), for suggestions and older accounts. */
    public static function suggest(?string $name, ?string $email = null): string
    {
        $base = self::slug($name);

        if (strlen($base) < self::MIN || ! preg_match('/[a-z]/', $base)) {
            $base = self::slug(Str::before((string) $email, '@'));
        }

        if (strlen($base) < self::MIN || ! preg_match('/[a-z]/', $base)) {
            $base = 'traveller';
        }

        $base = substr($base, 0, self::MAX - 5);
        $candidates = [$base];

        foreach (range(1, 8) as $attempt) {
            $candidates[] = $base.random_int(10, 9999);
        }

        foreach ($candidates as $candidate) {
            if (! self::isReserved($candidate) && ! User::query()->where('username', $candidate)->exists()) {
                return $candidate;
            }
        }

        return substr($base, 0, self::MAX - 9).'_'.Str::lower(Str::random(8));
    }

    private static function slug(?string $value): string
    {
        return (string) preg_replace('/[^a-z0-9]+/', '', Str::lower(Str::ascii((string) $value)));
    }
}
