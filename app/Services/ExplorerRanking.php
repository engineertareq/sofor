<?php

namespace App\Services;

use App\Models\User;
use App\Models\VisitedPlace;
use App\Support\TravelMap;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The Explorers leaderboard: travellers ranked by how many areas of a map
 * they have been to. Ties go to whoever reached that number first.
 *
 * Only public, active traveller profiles take part, so a private profile
 * never appears anywhere on the board.
 */
class ExplorerRanking
{
    /**
     * Everybody on the board for this map, best first. Worked out fresh on
     * every call (nothing is kept between requests), so callers that need it
     * twice should hold on to the result.
     *
     * @return Collection<int, array{rank: int, user_id: int, visited: int, reached_at: string}>
     */
    public function standings(TravelMap $map): Collection
    {
        return $this->eligible($map)
            ->selectRaw('visited_places.user_id as user_id, COUNT(*) as visited, MAX(visited_places.created_at) as reached_at')
            ->groupBy('visited_places.user_id')
            ->orderByDesc('visited')
            ->orderBy('reached_at')
            ->orderBy('visited_places.user_id')
            ->get()
            ->values()
            ->map(fn (VisitedPlace $row, int $index) => [
                'rank' => $index + 1,
                'user_id' => (int) $row->user_id,
                'visited' => (int) $row->visited,
                'reached_at' => (string) $row->reached_at,
            ]);
    }

    /** @return array{rank: int, user_id: int, visited: int, reached_at: string}|null */
    public function positionOf(User $user, TravelMap $map): ?array
    {
        return $this->standings($map)->firstWhere('user_id', $user->id);
    }

    /** How many explorers are on this board. */
    public function count(TravelMap $map): int
    {
        return $this->eligible($map)->distinct()->count('visited_places.user_id');
    }

    /**
     * How many explorers have been to each area, most visited first.
     *
     * @return array<string, int>
     */
    public function popularity(TravelMap $map): array
    {
        return $this->eligible($map)
            ->selectRaw('visited_places.area as area, COUNT(*) as explorers')
            ->groupBy('visited_places.area')
            ->orderByDesc('explorers')
            ->orderBy('visited_places.area')
            ->pluck('explorers', 'area')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * Groups (divisions, continents) each of these travellers has reached.
     *
     * @param  list<int>  $userIds
     * @return array<int, int> user id => groups visited
     */
    public function groupsVisited(TravelMap $map, array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $areas = $map->areas();

        return VisitedPlace::query()
            ->where('map', $map->key)
            ->whereIn('user_id', $userIds)
            ->get(['user_id', 'area'])
            ->groupBy('user_id')
            ->map(fn (Collection $places) => $places
                ->filter(fn (VisitedPlace $place) => isset($areas[$place->area]) && $map->isCounted($place->area))
                ->map(fn (VisitedPlace $place) => $areas[$place->area]['group'])
                ->unique()
                ->count())
            ->all();
    }

    /** Visits that count on this map, by travellers who can be listed. */
    private function eligible(TravelMap $map): Builder
    {
        $counted = array_keys(array_filter($map->areas(), fn (array $area) => ($area['counted'] ?? true) !== false));

        return VisitedPlace::query()
            ->join('users', 'users.id', '=', 'visited_places.user_id')
            ->where('visited_places.map', $map->key)
            ->whereIn('visited_places.area', $counted)
            ->where('users.role', '!=', 'admin')
            ->where('users.is_active', true)
            ->where('users.profile_public', true);
    }
}
