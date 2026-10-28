<?php

namespace App\Models;

use App\Enums\MemberRole;
use App\Enums\MemberStatus;
use App\Enums\Rsvp;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TripMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'trip_id',
        'user_id',
        'invited_by',
        'name',
        'email',
        'phone',
        'role',
        'status',
        'share_units',
        'include_in_split',
        'invite_token',
        'invited_at',
        'joined_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'role' => MemberRole::class,
            'status' => MemberStatus::class,
            'include_in_split' => 'boolean',
            'share_units' => 'integer',
            'invited_at' => 'datetime',
            'joined_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'rsvp' => Rsvp::class,
            'rsvp_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $member) {
            if ($member->email !== null) {
                $member->email = strtolower(trim($member->email));
            }

            if ($member->phone !== null) {
                $member->phone = Phone::normalize($member->phone);
            }
        });
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function shares(): HasMany
    {
        return $this->hasMany(ExpenseShare::class);
    }

    public function isOwner(): bool
    {
        return $this->role === MemberRole::Owner;
    }

    public function isActive(): bool
    {
        return $this->status === MemberStatus::Active;
    }

    /**
     * Somebody who travelled and therefore shares the costs. An invite that has
     * not been linked to an account yet still counts - they were on the trip.
     */
    public function isTravelling(): bool
    {
        return in_array($this->status, [MemberStatus::Active, MemberStatus::Invited], true);
    }

    public function isRegistered(): bool
    {
        return $this->user_id !== null;
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];
        $initials = '';

        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= mb_strtoupper(mb_substr($part, 0, 1));
        }

        return $initials !== '' ? $initials : '?';
    }

    public function avatarGradient(): string
    {
        $seed = $this->user_id ?? crc32($this->email ?? $this->phone ?? $this->name);

        return User::GRADIENTS[$seed % count(User::GRADIENTS)];
    }

    /** The linked account's photo. Load `user` with the members to avoid a query per row. */
    public function avatarUrl(): ?string
    {
        return $this->user?->avatarUrl();
    }

    public function contactLabel(): string
    {
        return $this->email ?? Phone::pretty($this->phone) ?? __('No contact yet');
    }

    /**
     * This traveller's personal pay link (/p/<token>): what they owe and where
     * to send it, no account needed. Short, so it fits in a text message.
     */
    public function payUrl(): string
    {
        if ($this->pay_token === null) {
            do {
                $token = \Illuminate\Support\Str::random(12);
            } while (self::query()->where('pay_token', $token)->exists());

            $this->forceFill(['pay_token' => $token])->saveQuietly();
        }

        return route('pay.show', $this->pay_token);
    }

    /** The private link this traveller shares with their family. Created on first use. */
    public function familyUrl(): string
    {
        if ($this->family_token === null) {
            $this->rotateFamilyToken();
        }

        return route('family.show', $this->family_token);
    }

    public function rotateFamilyToken(): void
    {
        do {
            $token = \Illuminate\Support\Str::random(20);
        } while (self::query()->where('family_token', $token)->exists());

        $this->forceFill(['family_token' => $token])->saveQuietly();
    }

    /** The phone to text: their account's, or the one the trip has for them. */
    public function textablePhone(): ?string
    {
        $phone = $this->user?->phone ?? $this->phone;

        return Phone::isValid($phone) ? Phone::normalize($phone) : null;
    }

    /** The email to write to, only if it is known to reach them. */
    public function mailableEmail(): ?string
    {
        if ($this->user !== null) {
            return $this->user->hasVerifiedEmail() ? $this->user->email : null;
        }

        return $this->email;
    }

    public function firstName(): string
    {
        return (string) strtok(trim((string) $this->name), ' ') ?: 'there';
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', MemberStatus::Active->value);
    }

    /** Pending invites that match a freshly registered account. */
    public static function pendingForContact(?string $email, ?string $phone): Builder
    {
        $email = $email !== null ? strtolower(trim($email)) : null;
        $phone = Phone::normalize($phone);

        return self::query()
            ->whereNull('user_id')
            ->whereIn('status', [MemberStatus::Invited->value, MemberStatus::Active->value])
            ->where(function (Builder $q) use ($email, $phone) {
                if ($email !== null) {
                    $q->orWhere('email', $email);
                }

                if ($phone !== null) {
                    $q->orWhere('phone', $phone);
                }

                if ($email === null && $phone === null) {
                    $q->whereRaw('1 = 0');
                }
            });
    }

    public function travellerDetail(): HasOne
    {
        return $this->hasOne(TravellerDetail::class);
    }
}
