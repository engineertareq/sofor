<?php

namespace App\Support;

use RuntimeException;

/**
 * One travel map: its labels from config/travel_maps.php and its geometry from
 * resources/maps/<key>.json. Read only; visits are stored in visited_places.
 */
final class TravelMap
{
    /** @var array<string, array<string, mixed>> areas keyed by id */
    private array $areas;

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $data
     */
    private function __construct(
        public readonly string $key,
        private readonly array $config,
        private readonly array $data,
    ) {
        $this->areas = collect($data['areas'])->keyBy('id')->all();
    }

    /** @param  array<string, mixed>  $config */
    public static function load(string $key, array $config): self
    {
        $file = resource_path('maps/'.$key.'.json');

        if (! is_file($file)) {
            throw new RuntimeException("Travel map [$key] has no geometry at $file. Run `npm run maps:build`.");
        }

        return new self($key, $config, json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR));
    }

    public function label(): string
    {
        return __($this->config['label']);
    }

    /** Singular or plural name of one area: "district", "countries". */
    public function unit(int $count = 1): string
    {
        return __($count === 1 ? $this->config['unit'] : $this->config['units']);
    }

    public function groupUnit(int $count = 1): string
    {
        return __($count === 1 ? $this->config['group'] : $this->config['groups']);
    }

    /**
     * Area and group names in the reader's language (resources/maps/names/<locale>.php).
     *
     * @return array<string, string>
     */
    private function localNames(string $part): array
    {
        static $names = [];
        $locale = app()->getLocale();

        if ($locale === 'en') {
            return [];
        }

        $names[$locale] ??= is_file($file = resource_path('maps/names/'.$locale.'.php')) ? require $file : [];

        return $names[$locale][$part] ?? [];
    }

    /** The ISO code of the country this map divides, for reporting up to the world map. */
    public function country(): ?string
    {
        return $this->config['country'] ?? null;
    }

    public function width(): int
    {
        return $this->data['width'];
    }

    public function height(): int
    {
        return $this->data['height'];
    }

    public function attribution(): string
    {
        return __($this->data['attribution']);
    }

    /** @return array<string, string> group code => name */
    public function groups(): array
    {
        return array_replace($this->data['groups'], array_intersect_key($this->localNames($this->key.'_groups'), $this->data['groups']));
    }

    /** @return array<string, array<string, mixed>> */
    public function areas(): array
    {
        return $this->areas;
    }

    public function has(string $id): bool
    {
        return isset($this->areas[$id]);
    }

    public function name(string $id): string
    {
        return $this->localNames($this->key)[$id] ?? $this->areas[$id]['name'] ?? $id;
    }

    /** Areas that count towards the total, e.g. the 195 countries; territories do not. */
    public function isCounted(string $id): bool
    {
        return ($this->areas[$id]['counted'] ?? true) !== false;
    }

    public function countedTotal(): int
    {
        return $this->data['counted'];
    }

    /**
     * What the page and the image need about each area, without the paths.
     *
     * @return list<array<string, mixed>>
     */
    public function index(): array
    {
        $names = $this->localNames($this->key);

        return array_values(array_map(
            fn (array $area) => ['name' => $names[$area['id']] ?? $area['name']] + array_diff_key($area, ['d' => true]),
            $this->areas,
        ));
    }

    /**
     * @param  list<string>  $visited  area ids
     * @return array{visited: int, total: int, percent: int, extra: int, groups_visited: int, groups_total: int}
     */
    public function stats(array $visited): array
    {
        $known = array_values(array_filter($visited, fn (string $id) => $this->has($id)));
        $counted = array_filter($known, fn (string $id) => $this->isCounted($id));
        $total = $this->countedTotal();

        return [
            'visited' => count($counted),
            'total' => $total,
            'percent' => $total > 0 ? (int) floor(count($counted) * 100 / $total) : 0,
            // Visited places that are not counted, like territories on the world map.
            'extra' => count($known) - count($counted),
            'groups_visited' => count(array_unique(array_map(fn (string $id) => $this->areas[$id]['group'], $known))),
            'groups_total' => count($this->groups()),
        ];
    }
}
