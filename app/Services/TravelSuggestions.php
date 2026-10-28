<?php

namespace App\Services;

use App\Models\Trip;
use App\Models\User;
use App\Support\TravelMap;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Reads a traveller's trips (title, destination, notes, currency) and works
 * out which districts and countries they point to, so the travel map can
 * offer them with one tap instead of the traveller searching for each.
 *
 * Words come from each map's area names plus resources/maps/places/<key>.php.
 */
class TravelSuggestions
{
    /** Country names that are also everyday English words or first names. */
    private const AMBIGUOUS = ['chad', 'jordan', 'jersey', 'guinea', 'georgia'];

    /** @var array<string, array{words: array<string, list<string>>, bangla: array<string, list<string>>}> */
    private array $gazetteers = [];

    public function __construct(private readonly TravelMaps $maps) {}

    /**
     * Areas the traveller's trips point to that are not on this map yet.
     *
     * @return list<array{id: string, name: string, trips: list<array{id: int, title: string}>}>
     */
    public function forUser(User $user, TravelMap $map): array
    {
        $visited = array_flip($this->maps->visited($user, $map));
        $found = [];

        foreach ($this->tripsOf($user) as $trip) {
            foreach ($this->match($trip, $map) as $id) {
                if (isset($visited[$id])) {
                    continue;
                }

                $found[$id] ??= ['id' => $id, 'name' => $map->name($id), 'trips' => []];
                $found[$id]['trips'][] = ['id' => $trip->id, 'title' => $trip->title];
            }
        }

        return array_values($found);
    }

    /**
     * What one trip adds to each map that the traveller does not have yet.
     *
     * @return array<string, list<string>> map key => area ids
     */
    public function forTrip(User $user, Trip $trip): array
    {
        $visited = $this->maps->visitedEverywhere($user);
        $missing = [];

        foreach ($this->maps->keys() as $key) {
            $new = array_values(array_diff($this->match($trip, $this->maps->get($key)), $visited[$key]));

            if ($new !== []) {
                $missing[$key] = $new;
            }
        }

        return $missing;
    }

    /** @return list<string> area ids on $map that this trip points to */
    public function match(Trip $trip, TravelMap $map): array
    {
        $raw = implode(' ', array_filter([$trip->title, $trip->destination, $trip->description]));
        $words = ' '.$this->normalize($raw).' ';
        $bangla = $this->bangla($raw);

        $ids = $this->find($map, $words, $bangla);

        if ($map->key === config('travel_maps.world')) {
            // A trip kept in baht or ringgit says where it happened.
            $country = config('travel_maps.currency_countries.'.$trip->currency);

            if ($country !== null) {
                $ids[] = $country;
            }

            // A district names its country too: Sajek means Bangladesh.
            foreach ($this->maps->keys() as $key) {
                $part = $this->maps->get($key);

                if ($part->country() !== null && $this->find($part, $words, $bangla) !== []) {
                    $ids[] = $part->country();
                }
            }
        }

        return array_values(array_unique(array_filter($ids, fn (string $id) => $map->has($id))));
    }

    /**
     * Places named in any text, in the order the text names them: a cost's
     * title and note, say ("Hanif bus, Dhaka - Khagrachari" is Dhaka, then
     * Khagrachari). Used to put a trip's days on its map.
     *
     * @return list<string> area ids on $map
     */
    public function inText(TravelMap $map, string $text): array
    {
        $words = ' '.$this->normalize($text).' ';
        $bangla = $this->bangla($text);
        $gazetteer = $this->gazetteer($map);
        $found = [];

        foreach ($gazetteer['words'] as $phrase => $areas) {
            $at = strpos($words, ' '.$phrase.' ');

            if ($at !== false) {
                foreach ($areas as $id) {
                    $found[$id] = min($found[$id] ?? PHP_INT_MAX, $at);
                }
            }
        }

        if ($bangla !== '') {
            foreach ($gazetteer['bangla'] as $phrase => $areas) {
                $at = mb_strpos($bangla, $phrase);

                if ($at !== false) {
                    foreach ($areas as $id) {
                        $found[$id] = min($found[$id] ?? PHP_INT_MAX, $at);
                    }
                }
            }
        }

        asort($found);

        return array_values(array_filter(array_keys($found), fn (string $id) => $map->has($id)));
    }

    /** @return Collection<int, Trip> trips the traveller is on, newest first */
    private function tripsOf(User $user): Collection
    {
        return Trip::query()
            ->visibleTo($user)
            ->latest('starts_on')
            ->latest('id')
            ->get(['id', 'title', 'destination', 'description', 'currency', 'starts_on']);
    }

    /** @return list<string> */
    private function find(TravelMap $map, string $words, string $bangla): array
    {
        $gazetteer = $this->gazetteer($map);
        $ids = [];

        foreach ($gazetteer['words'] as $phrase => $areas) {
            if (str_contains($words, ' '.$phrase.' ')) {
                array_push($ids, ...$areas);
            }
        }

        if ($bangla !== '') {
            foreach ($gazetteer['bangla'] as $phrase => $areas) {
                if (str_contains($bangla, $phrase)) {
                    array_push($ids, ...$areas);
                }
            }
        }

        return $ids;
    }

    /** @return array{words: array<string, list<string>>, bangla: array<string, list<string>>} */
    private function gazetteer(TravelMap $map): array
    {
        if (isset($this->gazetteers[$map->key])) {
            return $this->gazetteers[$map->key];
        }

        $entries = [];

        foreach ($map->areas() as $id => $area) {
            foreach ([$area['name'], ...($area['aliases'] ?? [])] as $name) {
                $entries[] = [$name, [$id]];
            }
        }

        $file = resource_path('maps/places/'.$map->key.'.php');

        foreach (is_file($file) ? require $file : [] as $phrase => $areas) {
            $entries[] = [$phrase, (array) $areas];
        }

        $gazetteer = ['words' => [], 'bangla' => []];

        foreach ($entries as [$phrase, $areas]) {
            if (preg_match('/[^\x00-\x7F]/', $phrase) && preg_match('/\p{Bengali}/u', $phrase)) {
                $gazetteer['bangla'][$this->bangla($phrase)] = $areas;

                continue;
            }

            $phrase = $this->normalize($phrase);

            // Too short to trust ("us", "uk"), or a word that is usually not a country.
            if (strlen($phrase) < 3 || in_array($phrase, self::AMBIGUOUS, true)) {
                continue;
            }

            $gazetteer['words'][$phrase] = array_values(array_unique([...($gazetteer['words'][$phrase] ?? []), ...$areas]));
        }

        return $this->gazetteers[$map->key] = $gazetteer;
    }

    /** "Cox's Bazar", "COXS-BAZAR" and "Gopalgonj" all become the same words. */
    private function normalize(string $text): string
    {
        $text = Str::lower(Str::ascii($text));
        $text = str_replace(["'", '`'], '', $text);
        $text = (string) preg_replace('/[^a-z0-9]+/', ' ', $text);
        // Both spellings are common in Bangladesh: Gopalgonj / Gopalganj.
        $text = str_replace('gonj', 'ganj', $text);

        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    /**
     * Bangla text with the three letters that have two encodings (ড়, ঢ়, য়)
     * written one way, so typed and pasted text compare equal.
     */
    private function bangla(string $text): string
    {
        if (! preg_match('/\p{Bengali}/u', $text)) {
            return '';
        }

        return str_replace(
            ["\u{09DC}", "\u{09DD}", "\u{09DF}"],
            ["\u{09A1}\u{09BC}", "\u{09A2}\u{09BC}", "\u{09AF}\u{09BC}"],
            mb_strtolower($text),
        );
    }
}
