<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ExplorerRanking;
use App\Services\TravelMaps;
use App\Support\Username;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * sofor.io/@username: a traveller's photo, name and travel maps. Contact
 * details, trips and money never appear here.
 */
class PublicProfileController extends Controller
{
    public function __construct(
        private readonly TravelMaps $maps,
        private readonly ExplorerRanking $ranking,
    ) {}

    public function show(Request $request, string $username): View|RedirectResponse
    {
        $canonical = Username::normalize($username);

        // One address per profile: /@Rifat goes to /@rifat.
        if ($canonical !== $username) {
            return redirect()->route('profile.public', $canonical, 301);
        }

        $user = User::query()->where('username', $canonical)->first();

        // Admin accounts are console only; paused accounts are hidden.
        abort_if($user === null || $user->isAdmin() || ! $user->is_active, 404);

        $isOwner = $request->user()?->is($user) ?? false;

        if (! $user->profile_public && ! $isOwner) {
            return view('profiles.private', ['user' => $user]);
        }

        $visited = $this->maps->visitedEverywhere($user);

        $tabs = collect($this->maps->keys())->map(fn (string $key) => [
            'key' => $key,
            'label' => $this->maps->get($key)->label(),
            'unit' => $this->maps->get($key)->unit(2),
            'groupUnit' => $this->maps->get($key)->groupUnit(2),
            'stats' => $this->maps->get($key)->stats($visited[$key]),
        ]);

        // Open the map asked for, else the first one with anything on it.
        $current = $request->query('map');

        if (! is_string($current) || ! in_array($current, $this->maps->keys(), true)) {
            $current = $tabs->first(fn (array $tab) => $tab['stats']['visited'] + $tab['stats']['extra'] > 0)['key']
                ?? $this->maps->keys()[0];
        }

        $map = $this->maps->get($current);

        // Their place on each Explorers board (only public profiles are ranked), and the board's size.
        $ranks = collect($this->maps->keys())
            ->map(fn (string $key) => [
                'key' => $key,
                'label' => $key === config('travel_maps.world') ? __('worldwide') : __('in :place', ['place' => $this->maps->get($key)->label()]),
                'rank' => $this->ranking->positionOf($user, $this->maps->get($key))['rank'] ?? null,
                'of' => $this->ranking->count($this->maps->get($key)),
            ])
            ->filter(fn (array $rank) => $rank['rank'] !== null)
            ->values()
            ->all();

        // A traveller looking at someone else's map sees what they have in common.
        $viewer = $request->user();
        $mine = $viewer !== null && ! $isOwner && ! $viewer->isAdmin()
            ? $this->maps->visitedEverywhere($viewer)[$current]
            : null;

        return view('profiles.show', [
            'user' => $user,
            'isOwner' => $isOwner,
            'ranks' => $ranks,
            'tabs' => $tabs->all(),
            'map' => $map,
            'visited' => $visited[$current],
            'mine' => $mine,
            'hasAnything' => collect($visited)->flatten()->isNotEmpty(),
        ]);
    }
}
