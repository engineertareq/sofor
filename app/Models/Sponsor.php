<?php

namespace App\Models;

use App\Services\SponsorLogos;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A brand that sponsors Sofor. Admins add them from the console; live ones
 * scroll past in the "Sponsored by" strip on the home page and are listed,
 * by tier, on /sponsors. The logo is a PNG on the public media disk
 * (Cloudflare R2 or local), see SponsorLogos.
 */
class Sponsor extends Model
{
    /**
     * Highest first. The order here is the order everywhere: the carousel,
     * the sponsors page and the console.
     */
    public const TIERS = [
        'platinum' => 'Platinum',
        'gold' => 'Gold',
        'silver' => 'Silver',
    ];

    protected $fillable = [
        'name',
        'website',
        'tier',
        'tagline',
        'logo_path',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @param  Builder<Sponsor>  $query */
    public function scopeLive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Tier first, then the order set in the console. A CASE rather than FIELD()
     * so it runs on sqlite (tests) as well as MySQL.
     *
     * @param  Builder<Sponsor>  $query
     */
    public function scopeShowcase(Builder $query): void
    {
        $case = collect(array_keys(self::TIERS))
            ->map(fn (string $tier, int $rank) => "WHEN '{$tier}' THEN {$rank}")
            ->implode(' ');

        $query->orderByRaw("CASE tier {$case} ELSE ".count(self::TIERS).' END')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function tierLabel(): string
    {
        return self::TIERS[$this->tier] ?? Str::headline($this->tier);
    }

    public function logoUrl(): string
    {
        return SponsorLogos::url($this->logo_path);
    }

    /** "example.com" for display, without the scheme or a leading www. */
    public function websiteHost(): ?string
    {
        if (blank($this->website)) {
            return null;
        }

        $host = parse_url($this->website, PHP_URL_HOST) ?: $this->website;

        return Str::of($host)->lower()->replaceStart('www.', '')->toString();
    }
}
