<?php

namespace App\Models;

use App\Enums\TourBookingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Somebody's booking on a tour: a package, how many of it, and what that
 * came to when they booked. Their own page is /r/<token>, no account needed.
 *
 * What they have paid is worked out from their confirmed payments, less any
 * refund (load `payments` first when listing many).
 */
class TourRegistration extends Model
{
    public const STATE_PAID = 'paid';

    public const STATE_PARTLY = 'partly';

    public const STATE_UNPAID = 'unpaid';

    protected $fillable = [
        'tour_package_id',
        'user_id',
        'name',
        'phone',
        'email',
        'quantity',
        'seats',
        'unit_price',
        'total',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'status' => TourBookingStatus::class,
            'quantity' => 'integer',
            'seats' => 'integer',
            'unit_price' => 'decimal:2',
            'total' => 'decimal:2',
            'cancelled_at' => 'datetime',
            'reminded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (TourRegistration $registration) {
            $registration->token ??= Str::random(32);
            $registration->status ??= TourBookingStatus::Booked;
        });
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(TourPackage::class, 'tour_package_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(TourPayment::class)->latest('paid_on')->latest('id');
    }

    public function url(): string
    {
        return route('tours.ticket', $this->token);
    }

    public function firstName(): string
    {
        return Str::before(trim($this->name), ' ') ?: $this->name;
    }

    public function isBooked(): bool
    {
        return $this->status === TourBookingStatus::Booked;
    }

    /** Confirmed payments less confirmed refunds. */
    public function paid(): float
    {
        return round($this->payments
            ->where('status', TourPayment::STATUS_CONFIRMED)
            ->sum(fn (TourPayment $payment) => $payment->signedAmount()), 2);
    }

    /** Still to pay on a booking; nothing once it is cancelled or waiting for a seat. */
    public function due(): float
    {
        return $this->isBooked() ? max(0.0, round((float) $this->total - $this->paid(), 2)) : 0.0;
    }

    /** What holds the booking (the package's advance, for each one booked), less what is paid. */
    public function advanceDue(): float
    {
        $advance = $this->package ? $this->package->advanceAmount() * $this->quantity : (float) $this->total;

        return $this->isBooked() ? max(0.0, round($advance - $this->paid(), 2)) : 0.0;
    }

    /** Money the organiser still holds from a booking that was cancelled: a refund to make. */
    public function refundOwed(): float
    {
        return $this->status === TourBookingStatus::Cancelled ? max(0.0, $this->paid()) : 0.0;
    }

    public function paymentState(): string
    {
        $paid = $this->paid();

        return match (true) {
            $paid >= (float) $this->total - 0.009 => self::STATE_PAID,
            $paid > 0 => self::STATE_PARTLY,
            default => self::STATE_UNPAID,
        };
    }

    public function hasPendingPayment(): bool
    {
        return $this->payments->contains('status', TourPayment::STATUS_PENDING);
    }
}
