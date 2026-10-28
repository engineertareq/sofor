<?php

namespace App\Http\Controllers;

use App\Services\BrandingService;
use App\Services\TravelMaps;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * /travel-map: the public map maker. Anyone can mark where they have been
 * and download the map as a picture.
 *
 * A visitor's marks stay in their browser - no account, nothing stored
 * here. A signed-in traveller sees their own saved map instead, and every
 * tap saves to it, exactly as on their travel map page.
 */
class MapMakerController extends Controller
{
    public function __construct(private readonly TravelMaps $maps) {}

    public function __invoke(Request $request, BrandingService $branding, ?string $map = null): View
    {
        $keys = $this->maps->keys();
        $travelMap = $this->maps->get($map ?? $keys[0]);
        $user = $request->user();

        // Only a traveller account with a confirmed email or phone keeps a travel map.
        $traveller = $user !== null && ! $user->isAdmin();
        $account = $traveller && $user->hasVerifiedContact();
        $visited = $account ? $this->maps->visitedEverywhere($user) : array_fill_keys($keys, []);

        return view('travel.maker', [
            'map' => $travelMap,
            'account' => $account,
            'needsConfirm' => $traveller && ! $account,
            'visited' => $visited[$travelMap->key],
            'tabs' => collect($keys)->map(fn (string $key) => [
                'key' => $key,
                'label' => $this->maps->get($key)->label(),
                'total' => $this->maps->get($key)->countedTotal(),
                'visited' => $this->maps->get($key)->stats($visited[$key])['visited'],
            ])->all(),
            'logoUrl' => $branding->logoUrl(),
            'canonical' => $map === null || $map === $keys[0] ? route('map-maker') : route('map-maker', $travelMap->key),
        ]);
    }
}
