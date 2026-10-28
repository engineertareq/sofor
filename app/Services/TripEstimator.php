<?php

namespace App\Services;

use App\Enums\ExpenseCategory;
use App\Models\Expense;
use App\Models\Trip;
use App\Models\TripMember;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * "What does a trip there usually cost?" Answered from ended Sofor trips to
 * the same place, in the same currency: the typical cost per person, per
 * day, and how it splits between transport, rooms, food and the rest.
 *
 * Only aggregates are shown, and only when at least MIN_TRIPS trips match,
 * so no single group's spending can be read back out of an estimate.
 */
class TripEstimator
{
    public const MIN_TRIPS = 3;

    private const CANDIDATES = 400;

    private const CACHE_SECONDS = 6 * 3600;

    /**
     * @return array<string, mixed>|null
     */
    public function estimate(?string $destination, string $currency = 'BDT', ?int $days = null, ?int $excludeTripId = null): ?array
    {
        $key = self::placeKey($destination);

        if ($key === null) {
            return null;
        }

        $rows = collect(Cache::remember(
            'trip-estimate:v1:'.md5($key.'|'.$currency),
            self::CACHE_SECONDS,
            fn () => $this->rows((string) $destination, $key, $currency),
        ))->reject(fn (array $row) => $row['trip_id'] === $excludeTripId)->values();

        if ($rows->count() < self::MIN_TRIPS) {
            return null;
        }

        $perPerson = $rows->pluck('per_person')->sort()->values();
        $dated = $rows->whereNotNull('days');
        $perDay = $dated->count() >= self::MIN_TRIPS ? self::median($dated->pluck('per_day')) : null;
        $typicalDays = $dated->count() >= self::MIN_TRIPS ? (int) round(self::median($dated->pluck('days'))) : null;
        $days = $days !== null && $days >= 1 && $days <= 60 ? $days : null;

        $median = self::median($perPerson);
        $estimate = $days !== null && $perDay !== null ? $perDay * $days : $median;
        // The usual spread (middle half of trips), moved to the chosen length.
        $scale = $median > 0 ? $estimate / $median : 1;

        // Category mix, pooled over every matched trip.
        $pooled = [];
        foreach ($rows as $row) {
            foreach ($row['categories'] as $category => $amount) {
                $pooled[$category] = ($pooled[$category] ?? 0) + $amount;
            }
        }
        $pooledTotal = array_sum($pooled) ?: 1;
        arsort($pooled);

        return [
            'trips' => $rows->count(),
            'currency' => $currency,
            'per_person' => self::round($estimate),
            'low' => self::round(self::percentile($perPerson, 25) * $scale),
            'high' => self::round(self::percentile($perPerson, 75) * $scale),
            'per_day' => $perDay !== null ? self::round($perDay) : null,
            'days' => $days ?? $typicalDays,
            'for_days' => $days !== null && $perDay !== null,
            'categories' => collect($pooled)->take(4)->map(function (float $amount, string $value) use ($pooledTotal, $estimate) {
                $category = ExpenseCategory::tryFrom($value) ?? ExpenseCategory::Other;

                return [
                    'label' => $category->label(),
                    'icon' => \App\Support\Glyphs::svg($category->icon(), 'inline-block size-3.5 align-[-2px]'),
                    'percent' => round($amount / $pooledTotal * 100),
                    'amount' => self::round($estimate * $amount / $pooledTotal),
                ];
            })->values()->all(),
        ];
    }

    /**
     * One row per matching ended trip: spend per share unit, trip length and
     * the category totals per unit.
     *
     * @return array<int, array<string, mixed>>
     */
    private function rows(string $destination, string $key, string $currency): array
    {
        $needle = self::searchWord($destination);

        $trips = Trip::query()
            ->ended()
            ->where('currency', $currency)
            ->whereNotNull('destination')
            ->when($needle !== null, fn ($query) => $query->where('destination', 'like', '%'.$needle.'%'))
            ->latest('ended_at')
            ->limit(self::CANDIDATES)
            ->get(['id', 'destination', 'starts_on', 'ends_on'])
            ->filter(fn (Trip $trip) => self::placeKey($trip->destination) === $key);

        if ($trips->isEmpty()) {
            return [];
        }

        $ids = $trips->pluck('id')->all();

        $units = TripMember::query()
            ->whereIn('trip_id', $ids)
            ->where('include_in_split', true)
            ->whereIn('status', ['active', 'invited'])
            ->selectRaw('trip_id, SUM(share_units) as units')
            ->groupBy('trip_id')
            ->pluck('units', 'trip_id');

        $categories = Expense::query()
            ->whereIn('trip_id', $ids)
            ->selectRaw('trip_id, category, SUM(amount) as total')
            ->groupBy('trip_id', 'category')
            ->get()
            ->groupBy('trip_id');

        return $trips->map(function (Trip $trip) use ($units, $categories) {
            $tripUnits = (float) ($units[$trip->id] ?? 0);
            $byCategory = ($categories[$trip->id] ?? collect())
                ->mapWithKeys(fn ($row) => [self::categoryValue($row->category) => (float) $row->total]);
            $spent = (float) $byCategory->sum();

            if ($tripUnits < 1 || $spent <= 0) {
                return null;
            }

            $days = $trip->starts_on && $trip->ends_on ? $trip->starts_on->diffInDays($trip->ends_on) + 1 : null;
            $days = $days !== null && $days >= 1 && $days <= 60 ? (int) $days : null;
            $perPerson = $spent / $tripUnits;

            return [
                'trip_id' => $trip->id,
                'per_person' => $perPerson,
                'days' => $days,
                'per_day' => $days ? $perPerson / $days : null,
                'categories' => $byCategory->map(fn (float $total) => $total / $tripUnits)->all(),
            ];
        })->filter()->values()->all();
    }

    /**
     * "Cox's Bazar, Chattogram", "coxs bazar" and "COX'S BAZAR" are one
     * place: the part before the first comma or dash, letters and digits only.
     */
    public static function placeKey(?string $destination): ?string
    {
        if (blank($destination)) {
            return null;
        }

        $place = preg_split('/\s*(?:,|\s-\s|\s–\s|\/|\|)\s*/u', trim($destination))[0] ?? '';
        $key = preg_replace('/[^\pL\pM\pN]+/u', '', mb_strtolower($place)) ?? '';

        return mb_strlen($key) >= 3 ? $key : null;
    }

    /** The longest plain word of the place name, to narrow the query before matching keys. */
    private static function searchWord(string $destination): ?string
    {
        $place = preg_split('/\s*(?:,|\s-\s|\s–\s|\/|\|)\s*/u', trim($destination))[0] ?? '';

        return collect(preg_split('/[^\pL\pM\pN]+/u', $place) ?: [])
            ->filter(fn (string $word) => mb_strlen($word) >= 3)
            ->sortByDesc(fn (string $word) => mb_strlen($word))
            ->map(fn (string $word) => Str::lower($word))
            ->first();
    }

    private static function categoryValue(mixed $category): string
    {
        return $category instanceof ExpenseCategory ? $category->value : (string) $category;
    }

    /** @param  Collection<int, float>  $values */
    private static function median(Collection $values): float
    {
        return self::percentile($values->sort()->values(), 50);
    }

    /** @param  Collection<int, float>  $sorted */
    private static function percentile(Collection $sorted, int $percent): float
    {
        $sorted = $sorted->sort()->values();
        $count = $sorted->count();

        if ($count === 0) {
            return 0.0;
        }

        $position = ($count - 1) * $percent / 100;
        $lower = (int) floor($position);
        $upper = (int) ceil($position);

        return (float) $sorted[$lower] + ((float) $sorted[$upper] - (float) $sorted[$lower]) * ($position - $lower);
    }

    /** Estimates are rough by nature: round to the nearest 50. */
    private static function round(float $value): float
    {
        return $value >= 100 ? round($value / 50) * 50 : round($value);
    }
}
