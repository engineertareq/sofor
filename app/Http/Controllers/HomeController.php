<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Models\User;
use App\Services\ExplorerRanking;
use App\Services\SponsorShowcase;
use App\Services\TravelMaps;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * The journey the hero map draws: Dhaka up to the tea gardens, then down
     * the hills to the sea. Real district codes, so it lands on the real map.
     */
    private const HERO_ROUTE = ['BD-13', 'BD-60', 'BD-38', 'BD-10', 'BD-56', 'BD-01', 'BD-11'];

    public function __invoke(TravelMaps $maps, ExplorerRanking $ranking, SponsorShowcase $sponsors): View
    {
        $stats = [
            'travellers' => User::count(),
            'trips' => Trip::count(),
            'tracked' => (float) DB::table('expenses')->whereNull('deleted_at')->sum('amount'),
            'active_trips' => Trip::active()->count(),
        ];

        $bangladesh = $maps->get('bd') ?? $maps->get($maps->keys()[0]);

        // The Explorers spotlight only ever shows public profiles.
        $standings = $ranking->standings($bangladesh);
        $top = $standings->take(3);
        $people = User::query()->whereIn('id', $top->pluck('user_id'))->get()->keyBy('id');

        // The Explorers card draws Bangladesh as a constellation: a star at each
        // district's centre, brighter the more explorers have been there.
        $popularity = $ranking->popularity($bangladesh);
        $mostPopular = max([1, ...array_values($popularity)]);
        $stars = collect($bangladesh->areas())
            ->filter(fn (array $area, string $id) => $bangladesh->isCounted($id))
            ->map(fn (array $area, string $id) => [round($area['cx']), round($area['cy']), round(($popularity[$id] ?? 0) / $mostPopular, 2)])
            ->values()
            ->all();

        return view('home', [
            'stats' => $stats,
            'heroMap' => $bangladesh,
            'sponsors' => $sponsors->live(),
            'heroRoute' => array_values(array_filter(self::HERO_ROUTE, fn (string $id) => $bangladesh->has($id))),
            'explorers' => [
                'count' => $standings->count(),
                'covered' => count($popularity),
                'stars' => $stars,
                'sky' => [$bangladesh->width(), $bangladesh->height()],
                'total' => $bangladesh->countedTotal(),
                'top' => $top->map(fn (array $entry) => $entry + ['user' => $people[$entry['user_id']]])->values()->all(),
            ],
        ]);
    }
}
