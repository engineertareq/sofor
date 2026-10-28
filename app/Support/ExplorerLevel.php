<?php

namespace App\Support;

/**
 * Explorer levels on a travel map: Newcomer, Voyager, Trailblazer, Pathfinder,
 * Legend. They give everyone on the Explorers board a next goal.
 *
 * On Bangladesh's 64 districts the levels start at a share of the map
 * (10%, 25%, 50%, 75%). Nobody gets near three quarters of the world's
 * countries, so a map that large uses fixed counts instead.
 */
final class ExplorerLevel
{
    private const LEVELS = [
        ['key' => 'newcomer', 'icon' => 'backpack', 'label' => 'Newcomer'],
        ['key' => 'voyager', 'icon' => 'roadtrip', 'label' => 'Voyager'],
        ['key' => 'trailblazer', 'icon' => 'compass', 'label' => 'Trailblazer'],
        ['key' => 'pathfinder', 'icon' => 'hills', 'label' => 'Pathfinder'],
        ['key' => 'legend', 'icon' => 'peak', 'label' => 'Legend'],
    ];

    /**
     * Every level, lowest first, with the places it takes to reach it.
     *
     * @return list<array{key: string, icon: string, label: string, from: int}>
     */
    public static function all(int $total): array
    {
        $starts = self::starts($total);

        return array_map(
            fn (array $level, int $index) => ['label' => __($level['label']), 'from' => $starts[$index]] + $level,
            self::LEVELS,
            array_keys(self::LEVELS),
        );
    }

    /** @return array{key: string, icon: string, label: string, from: int} */
    public static function for(int $visited, int $total): array
    {
        $found = null;

        foreach (self::all($total) as $level) {
            if ($visited >= $level['from']) {
                $found = $level;
            }
        }

        return $found ?? self::all($total)[0];
    }

    /**
     * How many explorers sit at each level.
     *
     * @param  iterable<array{visited: int}>  $standings
     * @return array<string, int>
     */
    public static function tally(iterable $standings, int $total): array
    {
        $counts = array_fill_keys(array_column(self::LEVELS, 'key'), 0);

        foreach ($standings as $row) {
            $counts[self::for((int) $row['visited'], $total)['key']]++;
        }

        return $counts;
    }

    /** @return list<int> where each level starts, in places visited */
    private static function starts(int $total): array
    {
        if ($total >= 150) {
            return [1, 5, 15, 40, 80];
        }

        return array_map(fn (int $percent) => max(1, (int) ceil($total * $percent / 100)), [0, 10, 25, 50, 75]);
    }
}
