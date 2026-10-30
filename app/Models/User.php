<?php

namespace App\Models;

use App\Http\Middleware\SetLocale;
use App\Notifications\ResetPasswordLink;
use App\Services\AvatarService;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements HasLocalePreference
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** Avatar gradients, kept in the Sofor palette so initials never clash
     *  with the teal and marigold the rest of the interface uses. */
    public const GRADIENTS = [
        'linear-gradient(135deg,#0f6f6a,#3aa79b)',
        'linear-gradient(135deg,#f2a71b,#e0742f)',
        'linear-gradient(135deg,#2f7fd0,#0f6f6a)',
        'linear-gradient(135deg,#7c5cf0,#c026a3)',
        'linear-gradient(135deg,#0d7a4a,#8bbf3d)',
        'linear-gradient(135deg,#d1542b,#f2a71b)',
        'linear-gradient(135deg,#0f8b8d,#2f7fd0)',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'profile_public',
        'email',
        'phone',
        'password',
        'timezone',
        'last_seen_at',
    ];

    // Deliberately not fillable: role, is_active, email_verified_at,
    // phone_verified_at and sms_balance. They grant access or money, so they
    // are only ever set with forceFill by the code that owns them.

    /** Same defaults as the table, so a fresh model behaves like a saved one. */
    protected $attributes = [
        'profile_public' => true,
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'travel_profile',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'travel_profile' => 'encrypted:array',
            'phone_verified_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'sms_balance' => 'decimal:2',
            'is_active' => 'boolean',
            'profile_public' => 'boolean',
            'avatar_crop' => 'array',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        static::deleted(function (self $user) {
            AvatarService::discard($user->avatar_path);
            AvatarService::discard($user->avatar_original_path);
        });
    }

    /** Where this traveller wants to be paid, the main one first. */
    public function payoutMethods(): HasMany
    {
        return $this->hasMany(PayoutMethod::class)->orderByDesc('is_primary')->orderBy('id');
    }

    public function smsTopups(): HasMany
    {
        return $this->hasMany(SmsTopup::class);
    }

    public function smsTransactions(): HasMany
    {
        return $this->hasMany(SmsTransaction::class);
    }

    public function ownedTrips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    /** Organised tours this traveller sells seats on. */
    public function tours(): HasMany
    {
        return $this->hasMany(Tour::class)->latest();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TripMember::class);
    }

    public function visitedPlaces(): HasMany
    {
        return $this->hasMany(VisitedPlace::class);
    }

    public function trips(): BelongsToMany
    {
        return $this->belongsToMany(Trip::class, 'trip_members')
            ->withPivot(['role', 'status'])
            ->withTimestamps();
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /** Admins work from the console only; everybody else from the traveller app. */
    public function homeUrl(): string
    {
        return $this->isAdmin() ? route('admin.dashboard') : route('dashboard');
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];
        $initials = '';

        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= mb_strtoupper(mb_substr($part, 0, 1));
        }

        return $initials !== '' ? $initials : 'S';
    }

    public function avatarGradient(): string
    {
        $seed = $this->id ?? crc32($this->email ?? $this->phone ?? $this->name);

        return self::GRADIENTS[$seed % count(self::GRADIENTS)];
    }

    /**
     * The uploaded profile photo, or null to fall back to initials. The path is
     * deliberately not fillable - only AvatarService writes it.
     */
    public function avatarUrl(): ?string
    {
        return $this->avatar_path !== null
            ? route('avatars.show', basename($this->avatar_path))
            : null;
    }

    /** sofor.io/@username */
    public function publicUrl(): string
    {
        return route('profile.public', $this->username);
    }

    /**
     * Whether visitors can see this traveller's profile. Admin accounts are
     * console only, and paused accounts have nothing to show.
     */
    public function hasPublicProfile(): bool
    {
        return ! $this->isAdmin() && $this->is_active && $this->profile_public;
    }

    public function handle(): string
    {
        return $this->email ?? Phone::pretty($this->phone) ?? __('Sofor traveller');
    }

    /**
     * The account that owns this email or phone, used for invite matching.
     * Only a verified contact counts: anybody can type somebody else's number
     * into the sign up form, so an unverified one proves nothing. Invites for
     * it wait until somebody verifies it (see claimPendingFor).
     */
    public static function findByContact(?string $email, ?string $phone): ?self
    {
        $email = filled($email) ? strtolower(trim((string) $email)) : null;
        $phone = Phone::normalize($phone);

        // With no contact details there is nothing to match on - returning a row
        // here would attach the invite to an arbitrary account.
        if ($email === null && $phone === null) {
            return null;
        }

        return self::query()
            ->when($email !== null, fn ($query) => $query->where('email', $email)->whereNotNull('email_verified_at'))
            ->when($email === null && $phone !== null, fn ($query) => $query->where('phone', $phone)->whereNotNull('phone_verified_at'))
            ->first();
    }

    /* ------------------------------------------------------------------
       Verification. An account must verify at least one way to reach it -
       its email (a link) or its phone (a texted code) - before it can use
       the traveller app; see EnsureContactIsVerified.
    ------------------------------------------------------------------ */

    public function hasVerifiedEmail(): bool
    {
        return $this->email !== null && $this->email_verified_at !== null;
    }

    public function hasVerifiedPhone(): bool
    {
        return $this->phone !== null && $this->phone_verified_at !== null;
    }

    public function hasVerifiedContact(): bool
    {
        return $this->hasVerifiedEmail() || $this->hasVerifiedPhone();
    }

    /** Whether some contact on the account still waits for verification. */
    public function hasUnverifiedContact(): bool
    {
        return ($this->email !== null && ! $this->hasVerifiedEmail())
            || ($this->phone !== null && ! $this->hasVerifiedPhone());
    }

    public function markEmailAsVerified(): bool
    {
        return $this->forceFill(['email_verified_at' => $this->freshTimestamp()])->save();
    }

    public function markPhoneAsVerified(): bool
    {
        return $this->forceFill(['phone_verified_at' => $this->freshTimestamp()])->save();
    }

    /** @param  string  $token */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordLink($token));
    }

    public function scopeSearch($query, ?string $term)
    {
        if (blank($term)) {
            return $query;
        }

        $term = '%'.trim($term).'%';

        return $query->where(fn ($q) => $q
            ->where('name', 'like', $term)
            ->orWhere('username', 'like', $term)
            ->orWhere('email', 'like', $term)
            ->orWhere('phone', 'like', $term));
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Emails and notifications are written in the language this traveller reads Sofor in. */
    public function preferredLocale(): ?string
    {
        return in_array($this->locale, ['en', 'bn'], true) ? $this->locale : SetLocale::defaultFor($this);
    }
}
