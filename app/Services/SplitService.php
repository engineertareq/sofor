<?php

namespace App\Services;

use App\Models\TripMember;
use Illuminate\Support\Collection;

/**
 * Splits an amount between trip members without ever losing a single poisha.
 * Amounts are handled in cents and the leftover cents are handed out with the
 * largest-remainder method, so ৳5000 across 3 people becomes
 * 1666.67 / 1666.67 / 1666.66 instead of a number nobody can explain.
 */
class SplitService
{
    public static function toCents(float|int|string|null $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    public static function toAmount(int $cents): float
    {
        return round($cents / 100, 2);
    }

    /**
     * @param  array<int, int>  $weights  memberId => weight (units)
     * @return array<int, int> memberId => cents
     */
    public function distribute(int $totalCents, array $weights): array
    {
        $weights = array_filter($weights, fn ($weight) => $weight > 0);

        if ($weights === []) {
            return [];
        }

        $totalWeight = array_sum($weights);
        $result = [];
        $remainders = [];
        $allocated = 0;

        foreach ($weights as $memberId => $weight) {
            $exact = $totalCents * $weight / $totalWeight;
            $base = (int) floor($exact);

            $result[$memberId] = $base;
            $remainders[$memberId] = $exact - $base;
            $allocated += $base;
        }

        $leftover = $totalCents - $allocated;

        if ($leftover > 0) {
            $order = array_keys($remainders);
            usort($order, function ($a, $b) use ($remainders) {
                return $remainders[$b] <=> $remainders[$a] ?: $a <=> $b;
            });

            foreach (array_slice($order, 0, $leftover) as $memberId) {
                $result[$memberId]++;
            }
        }

        return $result;
    }

    /**
     * Equal split of an expense between the given members.
     *
     * @param  Collection<int, TripMember>|array<int, TripMember>  $members
     * @param  array<int, int>|null  $units  optional memberId => units override
     * @return array<int, float> memberId => amount
     */
    public function split(float|int|string $amount, iterable $members, ?array $units = null): array
    {
        $weights = [];

        foreach ($members as $member) {
            $id = $member instanceof TripMember ? $member->id : (int) $member;

            if ($id <= 0) {
                continue;
            }

            $weight = $units[$id] ?? ($member instanceof TripMember ? ($member->share_units ?: 1) : 1);
            $weights[$id] = max(1, (int) $weight);
        }

        $cents = $this->distribute(self::toCents($amount), $weights);

        return array_map(fn (int $value) => self::toAmount($value), $cents);
    }
}
