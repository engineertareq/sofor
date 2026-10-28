<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Services\BrandingService;
use App\Services\ExplorerRanking;
use App\Services\TravelMaps;
use App\Services\TravelSuggestions;
use App\Support\TravelMap;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;

/**
 * The traveller's travel maps: Bangladesh by district and the world by
 * country, plus whatever config/travel_maps.php adds later.
 */
class TravelMapController extends Controller
{
    public function __construct(
        private readonly TravelMaps $maps,
        private readonly TravelSuggestions $suggestions,
    ) {}

    public function index(): RedirectResponse
    {
        return redirect()->route('travel.show', $this->maps->keys()[0]);
    }

    public function show(Request $request, string $map, BrandingService $branding, ExplorerRanking $ranking): View
    {
        $user = $request->user();
        $travelMap = $this->map($map);
        $visitedEverywhere = $this->maps->visitedEverywhere($user);

        return view('travel.show', [
            'rank' => $ranking->positionOf($user, $travelMap)['rank'] ?? null,
            'map' => $travelMap,
            'visited' => $visitedEverywhere[$map],
            'stats' => $travelMap->stats($visitedEverywhere[$map]),
            'tabs' => collect($this->maps->keys())->map(fn (string $key) => [
                'key' => $key,
                'label' => $this->maps->get($key)->label(),
                'stats' => $this->maps->get($key)->stats($visitedEverywhere[$key]),
            ])->all(),
            'suggestions' => $this->suggestions->forUser($user, $travelMap),
            'logoUrl' => $branding->logoUrl(),
        ]);
    }

    /** Mark one or more areas as visited, from the map, the list or a suggestion. */
    public function store(Request $request, string $map): JsonResponse|RedirectResponse
    {
        $travelMap = $this->map($map);

        $data = $request->validate([
            'areas' => ['required', 'array', 'max:300'],
            'areas.*' => ['string', 'max:12'],
            'trip' => ['nullable', 'integer'],
        ]);

        $unknown = array_values(array_filter($data['areas'], fn (string $id) => ! $travelMap->has($id)));

        if ($unknown !== []) {
            abort(422, 'Not on this map: '.implode(', ', $unknown));
        }

        $trip = $this->tripFor($request, $data['trip'] ?? null);
        $result = $this->maps->add($request->user(), $travelMap, $data['areas'], $trip);

        return $this->respond($request, $travelMap, $result['added'] === []
            ? __('Already on your map.')
            : $this->addedMessage($travelMap, $result['added'], $result['country']), $result);
    }

    public function destroy(Request $request, string $map, string $area): JsonResponse|RedirectResponse
    {
        $travelMap = $this->map($map);
        abort_unless($travelMap->has($area), 404);

        $this->maps->remove($request->user(), $travelMap, $area);

        return $this->respond($request, $travelMap, __(':area removed from your map.', ['area' => $travelMap->name($area)]));
    }

    public function clear(Request $request, string $map): RedirectResponse
    {
        $travelMap = $this->map($map);
        $count = $this->maps->clear($request->user(), $travelMap);

        return back()->with('status', $count > 0 ? __('Your :label map is empty again.', ['label' => $travelMap->label()]) : __('Your :label map was already empty.', ['label' => $travelMap->label()]));
    }

    /** "Add to my travel map" on a trip page: everything that trip points to, on every map. */
    public function fromTrip(Request $request, Trip $trip): RedirectResponse
    {
        $this->authorize('view', $trip);

        $user = $request->user();
        $names = [];

        foreach ($this->suggestions->forTrip($user, $trip) as $key => $areas) {
            $travelMap = $this->maps->get($key);
            $result = $this->maps->add($user, $travelMap, $areas, $trip);
            array_push($names, ...array_map(fn (string $id) => $travelMap->name($id), $result['added']));

            // Marked on the world map along the way, so the next loop finds it already there.
            if ($result['country'] !== null) {
                $names[] = $this->maps->world()->name($result['country']);
            }
        }

        return back()->with('status', $names === [] ? __('Everything from this trip is already on your travel map.') : __('Added :names to your travel map.', ['names' => Arr::join(array_values(array_unique($names)), ', ', ' '.__('and').' ')]));
    }

    private function map(string $key): TravelMap
    {
        return $this->maps->get($key) ?? abort(404);
    }

    /** Only a trip the traveller is on can be credited with a visit. */
    private function tripFor(Request $request, ?int $id): ?Trip
    {
        if ($id === null) {
            return null;
        }

        $trip = Trip::find($id);

        return $trip !== null && $request->user()->can('view', $trip) ? $trip : null;
    }

    /**
     * @param  list<string>  $added
     */
    private function addedMessage(TravelMap $map, array $added, ?string $country): string
    {
        $names = array_map(fn (string $id) => $map->name($id), $added);
        $message = __('Added :names to your map.', ['names' => Arr::join($names, ', ', ' '.__('and').' ')]);

        if ($country !== null) {
            $message .= ' '.__(':country is on your world map too.', ['country' => $this->maps->world()->name($country)]);
        }

        return $message;
    }

    /**
     * The page updates itself from JSON; a plain form gets redirected back.
     *
     * @param  array{added?: list<string>, country?: ?string}  $result
     */
    private function respond(Request $request, TravelMap $map, string $message, array $result = []): JsonResponse|RedirectResponse
    {
        if (! $request->expectsJson()) {
            return back()->with('status', $message);
        }

        $visited = $this->maps->visited($request->user(), $map);

        return response()->json([
            'message' => $message,
            'visited' => $visited,
            'added' => $result['added'] ?? [],
            'country' => $result['country'] ?? null,
            'stats' => $map->stats($visited),
        ]);
    }
}
