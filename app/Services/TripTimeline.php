<?php

namespace App\Services;

use App\Enums\ExpenseCategory;
use App\Enums\SplitType;
use App\Models\Expense;
use App\Models\Trip;
use Illuminate\Support\Carbon;

/**
 * A trip's costs as a story, day by day: what was spent, on what and why, and
 * how the total grew. Shown to the trip's travellers (/trips/{trip}/timeline)
 * and, when the leader shares it, to anyone with the link (/timeline/{token}).
 *
 * The public version carries no receipts, and no names when the leader has
 * switched "show who paid" off. Contact details never appear in either.
 */
class TripTimeline
{
    public function __construct(
        private readonly TravelMaps $maps,
        private readonly TravelSuggestions $places,
    ) {}

    /**
     * @return array{total: float, per_person: float|null, travellers: int, costs: int, days: int,
     *               categories: list<array<string, mixed>>, timeline: list<array<string, mixed>>, biggest: list<array<string, mixed>>}
     */
    public function build(Trip $trip, bool $public = false): array
    {
        $names = ! $public || $trip->timeline_names;

        $expenses = $trip->expenses()
            ->reorder()
            ->with(['payer.user', 'shares'])
            ->orderBy('spent_on')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $total = round((float) $expenses->sum('amount'), 2);
        $travellers = $trip->participants()->count();

        $items = $expenses->map(fn (Expense $expense) => $this->item($expense, $total, $names, $public));

        // Day by day, numbered from the first day of the trip (or of its first cost).
        $start = $trip->starts_on?->copy()->startOfDay() ?? $expenses->first()?->spent_on?->copy()->startOfDay();
        $end = $trip->ends_on?->copy()->startOfDay();
        $running = 0.0;
        $timeline = [];

        foreach ($items->groupBy(fn (array $item) => $item['date']->toDateString()) as $key => $dayItems) {
            $date = Carbon::parse($key);
            $dayTotal = round((float) $dayItems->sum('amount'), 2);
            $running = round($running + $dayTotal, 2);

            $kind = match (true) {
                $start !== null && $date->lt($start) => 'before',
                $end !== null && $date->gt($end) => 'after',
                default => 'day',
            };

            $timeline[] = [
                'key' => $key,
                'date' => $date,
                'kind' => $kind,
                'number' => $kind === 'day' && $start !== null ? (int) $start->diffInDays($date) + 1 : null,
                'total' => $dayTotal,
                'cumulative' => $running,
                'share' => $total > 0 ? round($running / $total * 100, 1) : 0.0,
                'mix' => $dayItems->groupBy(fn (array $item) => $item['category']->value)
                    ->map(fn ($group) => round((float) $group->sum('amount') / max($dayTotal, 0.01) * 100, 1))
                    ->sortDesc()
                    ->all(),
                'items' => $dayItems->values()->all(),
            ];
        }

        $categories = $items->groupBy(fn (array $item) => $item['category']->value)
            ->map(fn ($group, $value) => [
                'category' => ExpenseCategory::from($value),
                'total' => round((float) $group->sum('amount'), 2),
                'count' => $group->count(),
                'percent' => $total > 0 ? round((float) $group->sum('amount') / $total * 100, 1) : 0.0,
            ])
            ->sortByDesc('total')
            ->values()
            ->all();

        return [
            'total' => $total,
            'per_person' => $travellers > 0 ? round($total / $travellers, 2) : null,
            'travellers' => $travellers,
            'costs' => $expenses->count(),
            'days' => count(array_filter($timeline, fn (array $day) => $day['kind'] === 'day')),
            'categories' => $categories,
            'timeline' => $timeline,
            'biggest' => $items->sortByDesc('amount')->take(3)->values()->all(),
            'map' => $this->map($trip, $timeline),
        ];
    }

    /**
     * The trip on the map of Bangladesh, day by day. Each day's places come
     * from what its costs say ("Hanif bus, Dhaka - Khagrachari"); the trip's
     * own title and destination fill in. The route joins the places in the
     * order the trip reached them, and `marks` says how far along it each day
     * got, so the page can draw it on as the reader scrolls. The view is
     * zoomed to the region the trip covered.
     *
     * @param  list<array<string, mixed>>  $timeline
     * @return array<string, mixed>|null null when no place can be put on the map
     */
    private function map(Trip $trip, array $timeline): ?array
    {
        $map = $this->maps->get('bd');

        if ($map === null) {
            return null;
        }

        $areas = $map->areas();
        $tripPlaces = $this->places->match($trip, $map);
        $days = [];   // area id => day keys it shows up on
        $stops = [];  // area id => the first day it shows up on (null: from the trip itself)

        foreach ($timeline as $day) {
            foreach ($day['items'] as $item) {
                foreach ($this->places->inText($map, $item['title'].' '.($item['notes'] ?? '')) as $id) {
                    $days[$id][] = $day['key'];
                    $stops[$id] ??= $day;
                }
            }
        }

        foreach ($tripPlaces as $id) {
            $stops[$id] ??= null;
        }

        $stops = array_slice($stops, 0, 10, true);

        if ($stops === []) {
            return null;
        }

        $point = fn (string $id) => [(float) $areas[$id]['cx'], (float) $areas[$id]['cy']];

        // The route, and how far along it (0..1, by distance) each stop sits.
        $points = array_map($point, array_keys($stops));
        $route = 'M'.round($points[0][0], 1).','.round($points[0][1], 1);
        $along = [0.0];
        $length = 0.0;

        for ($i = 1; $i < count($points); $i++) {
            [$x1, $y1] = $points[$i - 1];
            [$x2, $y2] = $points[$i];
            $cx = ($x1 + $x2) / 2 - ($y2 - $y1) * 0.22;
            $cy = ($y1 + $y2) / 2 + ($x2 - $x1) * 0.22;
            $route .= ' Q'.round($cx, 1).','.round($cy, 1).' '.round($x2, 1).','.round($y2, 1);
            $length += hypot($x2 - $x1, $y2 - $y1);
            $along[] = $length;
        }

        $along = array_map(fn (float $at) => $length > 0 ? round($at / $length, 4) : 1.0, $along);

        // How far the route has got by the end of each day.
        $marks = [];
        $reached = 0.0;
        $ids = array_keys($stops);

        foreach ($timeline as $day) {
            foreach ($ids as $index => $id) {
                if (in_array($day['key'], $days[$id] ?? [], true)) {
                    $reached = max($reached, $along[$index]);
                }
            }

            $marks[$day['key']] = $reached;
        }

        // Zoomed to the region the trip covered, a little wider than tall.
        $xs = array_column($points, 0);
        $ys = array_column($points, 1);
        [$minX, $maxX, $minY, $maxY] = [min($xs) - 80, max($xs) + 80, min($ys) - 80, max($ys) + 80];
        $width = max($maxX - $minX, 300.0);
        $height = max($maxY - $minY, 260.0);
        $aspect = 1.1;
        $width = max($width, $height * $aspect);
        $height = max($height, $width / $aspect);
        $width = min($width, (float) $map->width());
        $height = min($height, (float) $map->height());
        $x = min(max(($minX + $maxX - $width) / 2, 0), $map->width() - $width);
        $y = min(max(($minY + $maxY - $height) / 2, 0), $map->height() - $height);

        return [
            'viewBox' => implode(' ', array_map(fn (float $v) => round($v, 1), [$x, $y, $width, $height])),
            'unit' => round($width / 100, 2),   // 1% of the view's width, for sizing pins and lines
            'areas' => collect($areas)
                ->filter(fn (array $area) => $area['d'] !== '')
                ->map(fn (array $area, string $id) => [
                    'id' => $id,
                    'd' => $area['d'],
                    'lit' => array_key_exists($id, $stops),
                    'days' => array_values(array_unique($days[$id] ?? [])),
                ])
                ->values()
                ->all(),
            'stops' => array_map(fn (string $id, int $index) => [
                'id' => $id,
                'name' => $map->name($id),
                'x' => $points[$index][0],
                'y' => $points[$index][1],
                'day' => $stops[$id]['key'] ?? null,
                'number' => $stops[$id] !== null && $stops[$id]['kind'] === 'day' ? $stops[$id]['number'] : null,
                'days' => array_values(array_unique($days[$id] ?? [])),
            ], $ids, array_keys($ids)),
            'route' => count($points) > 1 ? $route : null,
            'marks' => $marks,
        ];
    }

    /** @return array<string, mixed> */
    private function item(Expense $expense, float $total, bool $names, bool $public): array
    {
        $payer = $expense->payer;
        $shares = $expense->shares->count();
        $amount = (float) $expense->amount;

        return [
            'id' => $expense->id,
            'title' => $expense->title,
            'notes' => filled($expense->notes) ? trim((string) $expense->notes) : null,
            'category' => $expense->categoryEnum(),
            'amount' => $amount,
            'date' => $expense->spent_on->copy(),
            // A time only when the cost was logged on the day it was spent: then it is close to true.
            'time' => $expense->created_at && $expense->created_at->isSameDay($expense->spent_on) ? $expense->created_at->copy() : null,
            'payer' => $payer && $names ? [
                'name' => $payer->firstName(),
                'initials' => $payer->initials(),
                'gradient' => $payer->avatarGradient(),
                'avatar' => $payer->avatarUrl(),
            ] : null,
            'shares' => $shares,
            'each' => $shares > 0 && $expense->split_type === SplitType::Equal ? round($amount / $shares, 2) : null,
            'custom' => $expense->split_type !== SplitType::Equal,
            'items' => $expense->isItemised() ? count($expense->items) : 0,
            'big' => $total > 0 && $amount >= $total * 0.2 && $amount > 0,
            'receipt' => ! $public && $expense->hasReceipt() ? $expense->receiptUrl() : null,
        ];
    }
}
