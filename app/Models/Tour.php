<?php

namespace App\Models;

use App\Enums\TourBookingStatus;
use App\Enums\TourStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An organised tour: seats sold to the public at a fixed price, by a tour
 * group, a club or an office. Its public page is /t/<code>; everyone who
 * books gets their own page (TourRegistration::url). Money is paid straight
 * to the organiser - Sofor only keeps the record.
 */
class Tour extends Model
{
    protected $fillable = [
        'title',
        'destination',
        'description',
        'cover',
        'starts_on',
        'ends_on',
        'meeting_point',
        'meeting_at',
        'capacity',
        'closes_on',
        'contact_phone',
        'show_account',
    ];

    protected function casts(): array
    {
        return [
            'status' => TourStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'meeting_at' => 'datetime',
            'closes_on' => 'date',
            'capacity' => 'integer',
            'show_account' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Tour $tour) {
            $tour->code ??= self::newCode();
            $tour->status ??= TourStatus::Draft;
        });
    }

    /** Eight letters and digits, without the ones that read alike (0/O, 1/l/I). */
    public static function newCode(): string
    {
        do {
            $code = collect(range(1, 8))->map(fn () => 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789'[random_int(0, 54)])->implode('');
        } while (self::query()->where('code', $code)->exists());

        return $code;
    }

    public function organiser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function packages(): HasMany
    {
        return $this->hasMany(TourPackage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(TourRegistration::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(TourPayment::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(TourExpense::class);
    }

    public function coverStyle(): string
    {
        return Trip::COVERS[$this->cover] ?? Trip::COVERS['aurora'];
    }

    public function publicUrl(): string
    {
        return route('tours.public', $this->code);
    }

    /** Seats held by bookings, from the database (TourAccount counts the loaded ones). */
    public function seatsTaken(): int
    {
        return (int) $this->registrations()->where('status', TourBookingStatus::Booked)->sum('seats');
    }

    /** Null when the tour has no seat limit. */
    public function seatsLeft(): ?int
    {
        return $this->capacity === null ? null : max(0, $this->capacity - $this->seatsTaken());
    }

    /** Open, and not past the last day to register. */
    public function takesRegistrations(): bool
    {
        return $this->status === TourStatus::Open
            && ($this->closes_on === null || ! $this->closes_on->endOfDay()->isPast());
    }
}
