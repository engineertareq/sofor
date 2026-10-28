<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A "become a sponsor" request sent from /sponsors. Admins read them in the
 * console and move them along from new, to contacted, to closed.
 */
class SponsorInquiry extends Model
{
    public const STATUS_NEW = 'new';

    public const STATUS_CONTACTED = 'contacted';

    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [
        self::STATUS_NEW => 'New',
        self::STATUS_CONTACTED => 'Contacted',
        self::STATUS_CLOSED => 'Closed',
    ];

    protected $fillable = [
        'name',
        'company',
        'email',
        'phone',
        'website',
        'tier',
        'message',
        'status',
        'ip_address',
    ];

    /** @param  Builder<SponsorInquiry>  $query */
    public function scopeUnanswered(Builder $query): void
    {
        $query->where('status', self::STATUS_NEW);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function tierLabel(): string
    {
        return Sponsor::TIERS[$this->tier] ?? __('Not sure yet');
    }
}
