<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One change to an SMS balance. `amount` is signed: credits positive, texts negative. */
class SmsTransaction extends Model
{
    public const TYPE_TOPUP = 'topup';

    public const TYPE_CHARGE = 'charge';

    public const TYPE_REFUND = 'refund';

    protected $fillable = ['type', 'amount', 'balance_after', 'messages', 'description', 'recipient', 'trip_id', 'sms_topup_id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'messages' => 'integer',
        ];
    }

    /** The ledger line in the reader's language; the stored description stays in English. */
    public function label(): string
    {
        $description = (string) $this->description;

        return match (true) {
            preg_match('/^Payment reminder to (.+)$/', $description, $m) === 1 => __('Payment reminder to :name', ['name' => $m[1]]),
            preg_match('/^Refund: the text to (.+) could not be sent$/', $description, $m) === 1 => __('Refund: the text to :phone could not be sent', ['phone' => $m[1]]),
            default => __($description),
        };
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
