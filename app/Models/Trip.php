<?php

namespace App\Models;

use App\Enums\TripStatus;
use App\Support\Glyphs;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Trip extends Model
{
    use HasFactory;

    /**
     * Trip covers are light, travel-journal gradient bands. The keys are
     * stable because they are stored on the trip row, so only the values
     * changed when the redesign moved the app to a light-only theme.
     */
    public const COVERS = [
        'aurora' => 'linear-gradient(135deg,#dceafa 0%,#a8c8ee 100%)',
        'sunset' => 'linear-gradient(135deg,#fde5cf 0%,#f4b183 100%)',
        'mint' => 'linear-gradient(135deg,#d8f1ea 0%,#9fd8c7 100%)',
        'violet' => 'linear-gradient(135deg,#eae2fb 0%,#c4b1ef 100%)',
        'midnight' => 'linear-gradient(135deg,#e0e6f2 0%,#aab8d6 100%)',
        'forest' => 'linear-gradient(135deg,#e3f0d6 0%,#b1d98f 100%)',
    ];

    /** Human names for the cover swatches. The keys are stored on trips, so they stay stable. */
    public const COVER_LABELS = [
        'aurora' => 'Sky',
        'sunset' => 'Sunset',
        'mint' => 'Sea mint',
        'violet' => 'Lilac',
        'midnight' => 'Cool fog',
        'forest' => 'Tea leaf',
    ];

    public static function coverLabel(string $key): string
    {
        return __(self::COVER_LABELS[$key] ?? ucfirst($key));
    }

    protected $fillable = [
        'user_id',
        'title',
        'destination',
        'description',
        'cover',
        'emoji',
        'currency',
        'budget',
        'budget_per_person',
        'status',
        'invite_code',
        'starts_on',
        'ends_on',
        'ended_at',
        'last_activity_at',
    ];

    /**
     * The trip's picture (see Glyphs). The column is still called emoji:
     * trips from before the icons hold an emoji, read as the matching icon.
     */
    protected function icon(): Attribute
    {
        return Attribute::get(fn (): string => Glyphs::forTrip($this->emoji));
    }

    protected function casts(): array
    {
        return [
            'status' => TripStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'ended_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'budget' => 'decimal:2',
            'budget_per_person' => 'decimal:2',
            'budget_alert_level' => 'integer',
            'sms_reminders' => 'boolean',
            'checkpoint' => 'encrypted:array',
            'timeline_shared_at' => 'datetime',
            'timeline_names' => 'boolean',
        ];
    }

    /** Whether the cost timeline is open to anyone with its link. */
    public function timelineIsPublic(): bool
    {
        return $this->timeline_token !== null && $this->timeline_shared_at !== null;
    }

    /** The public timeline's address; null until the leader first shares it. */
    public function timelineUrl(): ?string
    {
        return $this->timeline_token !== null ? route('timeline.public', $this->timeline_token) : null;
    }

    /**
     * Open or close the public timeline. The token is kept while it is closed,
     * so opening it again brings the same link back.
     */
    public function shareTimeline(bool $shared): void
    {
        if ($shared && $this->timeline_token === null) {
            $this->timeline_token = self::freshTimelineToken();
        }

        $this->forceFill(['timeline_shared_at' => $shared ? ($this->timeline_shared_at ?? now()) : null])->save();
    }

    /** A new link; the old one stops working at once. */
    public function rotateTimelineToken(): void
    {
        $this->forceFill(['timeline_token' => self::freshTimelineToken()])->save();
    }

    public static function freshTimelineToken(): string
    {
        do {
            $token = Str::random(32);
        } while (self::where('timeline_token', $token)->exists());

        return $token;
    }

    protected static function booted(): void
    {
        static::creating(function (self $trip) {
            $trip->invite_code ??= self::freshInviteCode();
        });

        // Receipt photos live on the media disk, not in the database rows that cascade away.
        static::deleting(fn (self $trip) => \App\Services\ReceiptService::discardTrip($trip));
    }

    public static function freshInviteCode(): string
    {
        do {
            $code = Str::upper(Str::random(8));
        } while (self::where('invite_code', $code)->exists());

        return $code;
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(TripMember::class)->orderBy('id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(TripMember::class)
            ->where('include_in_split', true)
            ->whereIn('status', ['active', 'invited'])
            ->orderBy('id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class)->latest('spent_on')->latest('id');
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(Settlement::class)->latest('settled_on')->latest('id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(TripActivity::class)->latest('id');
    }

    public function collections(): HasMany
    {
        return $this->hasMany(TripCollection::class)->latest('id');
    }

    public function checkins(): HasMany
    {
        return $this->hasMany(TripCheckin::class)->latest('id');
    }

    public function polls(): HasMany
    {
        return $this->hasMany(TripPoll::class)->latest('id');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(PaymentReminder::class);
    }

    public function isActive(): bool
    {
        return $this->status === TripStatus::Active;
    }

    public function isEnded(): bool
    {
        return $this->status === TripStatus::Ended;
    }

    public function hasBudget(): bool
    {
        return (float) $this->budget_per_person > 0 || (float) $this->budget > 0;
    }

    /**
     * The group budget for the given number of share units. A per-person
     * budget grows with the group (a couple counts as two units); a group
     * budget is fixed.
     */
    public function budgetTotal(float $units): ?float
    {
        if ((float) $this->budget_per_person > 0) {
            return round((float) $this->budget_per_person * max(1, $units), 2);
        }

        return (float) $this->budget > 0 ? (float) $this->budget : null;
    }

    /**
     * The trip form sends one amount and whether it is per person or for the
     * whole group; only one of the two columns is ever set.
     *
     * @param  array<string, mixed>  $data
     * @return array{budget: float|null, budget_per_person: float|null}
     */
    public static function budgetAttributes(array $data): array
    {
        $perPerson = ($data['budget_mode'] ?? null) === 'person'
            || (! isset($data['budget_mode']) && filled($data['budget_per_person'] ?? null));

        $amount = $perPerson ? ($data['budget_per_person'] ?? $data['budget'] ?? null) : ($data['budget'] ?? null);
        $amount = filled($amount) && (float) $amount > 0 ? round((float) $amount, 2) : null;

        return [
            'budget' => $perPerson ? null : $amount,
            'budget_per_person' => $perPerson ? $amount : null,
        ];
    }

    public function coverStyle(): string
    {
        return self::COVERS[$this->cover] ?? self::COVERS['aurora'];
    }

    public function inviteUrl(): string
    {
        return route('trips.join.show', $this->invite_code);
    }

    public function markActivity(): void
    {
        $this->forceFill(['last_activity_at' => now()])->saveQuietly();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', TripStatus::Active->value);
    }

    public function scopeEnded(Builder $query): Builder
    {
        return $query->where('status', TripStatus::Ended->value);
    }

    /** Trips the user owns or is an accepted member of. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user) {
            $q->where('user_id', $user->id)
                ->orWhereHas('members', fn (Builder $m) => $m
                    ->where('user_id', $user->id)
                    ->whereIn('status', ['active', 'invited']));
        });
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $term = '%'.trim($term).'%';

        return $query->where(fn (Builder $q) => $q
            ->where('title', 'like', $term)
            ->orWhere('destination', 'like', $term));
    }

    /**
     * A cheap fingerprint of everything that changes the trip dashboard.
     * Used by the realtime stream to decide when to push a refresh.
     */
    public function activityStamp(): string
    {
        $expenses = Expense::withTrashed()
            ->where('trip_id', $this->id)
            ->selectRaw('COUNT(*) as total, COALESCE(MAX(updated_at), 0) as touched')
            ->first();

        $members = TripMember::where('trip_id', $this->id)
            ->selectRaw('COUNT(*) as total, COALESCE(MAX(updated_at), 0) as touched')
            ->first();

        $settlements = Settlement::where('trip_id', $this->id)
            ->selectRaw('COUNT(*) as total, COALESCE(MAX(updated_at), 0) as touched')
            ->first();

        return md5(implode('|', [
            'trip:'.$this->updated_at?->timestamp,
            'e:'.$expenses->total.':'.$expenses->touched,
            'm:'.$members->total.':'.$members->touched,
            's:'.$settlements->total.':'.$settlements->touched,
            'a:'.TripActivity::where('trip_id', $this->id)->count(),
            'c:'.TripCheckin::where('trip_id', $this->id)->max('id'),
            'p:'.TripPoll::where('trip_id', $this->id)->max('updated_at').':'.TripPollVote::whereIn('poll_id', TripPoll::where('trip_id', $this->id)->select('id'))->count(),
        ]));
    }
}
