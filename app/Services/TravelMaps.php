<?php

namespace App\Services;

use App\Models\Trip;
use App\Models\User;
use App\Models\VisitedPlace;
use App\Support\TravelMap;
use Illuminate\Support\Facades\DB;

/**
 * The travel maps a traveller fills in, and the visits stored against them.
 * Maps are configured in config/travel_maps.php; adding one needs no code here.
 */
class TravelMaps
{
    /** @var array<string, TravelMap> */
    private array $loaded = [];

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys(config('travel_maps.maps'));
    }

    public function get(string $key): ?TravelMap
    {
        $config = config("travel_maps.maps.$key");

        if ($config === null) {
            return null;
        }

        return $this->loaded[$key] ??= TravelMap::load($key, $config);
    }

    public function world(): TravelMap
    {
        return $this->get(config('travel_maps.world'));
    }

    /** @return list<string> area ids the user has visited on this map */
    public function visited(User $user, TravelMap $map): array
    {
        return VisitedPlace::query()
            ->where('user_id', $user->id)
            ->where('map', $map->key)
            ->pluck('area')
            ->all();
    }

    /** @return array<string, list<string>> every map's visited area ids */
    public function visitedEverywhere(User $user): array
    {
        $visited = array_fill_keys($this->keys(), []);

        VisitedPlace::query()
            ->where('user_id', $user->id)
            ->get(['map', 'area'])
            ->each(function (VisitedPlace $place) use (&$visited) {
                $visited[$place->map][] = $place->area;
            });

        return $visited;
    }

    /**
     * Mark areas as visited. Unknown ids are ignored and repeats are harmless.
     * Marking a district also marks its country on the world map.
     *
     * @param  list<string>  $areas
     * @return array{added: list<string>, country: ?string} what was new, and the country marked along the way
     */
    public function add(User $user, TravelMap $map, array $areas, ?Trip $trip = null): array
    {
        $areas = array_values(array_unique(array_filter($areas, fn ($id) => is_string($id) && $map->has($id))));

        return DB::transaction(function () use ($user, $map, $areas, $trip) {
            $added = $this->insert($user, $map, $areas, $trip);
            $country = null;

            $world = $this->world();

            if ($added !== [] && $map->country() !== null && $world->has($map->country())) {
                $country = $this->insert($user, $world, [$map->country()], $trip)[0] ?? null;
            }

            return ['added' => $added, 'country' => $country];
        });
    }

    public function remove(User $user, TravelMap $map, string $area): void
    {
        VisitedPlace::query()
            ->where('user_id', $user->id)
            ->where('map', $map->key)
            ->where('area', $area)
            ->delete();
    }

    public function clear(User $user, TravelMap $map): int
    {
        return VisitedPlace::query()->where('user_id', $user->id)->where('map', $map->key)->delete();
    }

    /**
     * @param  list<string>  $areas
     * @return list<string> the areas that were not there before
     */
    private function insert(User $user, TravelMap $map, array $areas, ?Trip $trip): array
    {
        $new = array_values(array_diff($areas, $this->visited($user, $map)));

        if ($new === []) {
            return [];
        }

        $now = now();

        // insertOrIgnore: two quick taps on the same district never collide.
        VisitedPlace::query()->insertOrIgnore(array_map(fn (string $area) => [
            'user_id' => $user->id,
            'map' => $map->key,
            'area' => $area,
            'trip_id' => $trip?->id,
            'created_at' => $now,
            'updated_at' => $now,
        ], $new));

        return $new;
    }
}
