<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Services\TravelMaps;
use App\Services\TravelSuggestions;
use App\Services\TripLedger;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The trip recap card: a 1080 x 1350 travel poster of the trip - where it went
 * on the map of Bangladesh, what it cost and where the money went - drawn as
 * SVG and turned into a PNG in the browser to download or share. It carries
 * no names - only the trip, the totals and where the money went.
 */
class TripRecapController extends Controller
{
    public function __invoke(Request $request, Trip $trip, TripLedger $ledger, TravelMaps $maps, TravelSuggestions $suggestions): View
    {
        $this->authorize('view', $trip);

        $summary = $ledger->summarize($trip, $request->user());
        $stats = $summary['stats'];

        // Where in Bangladesh the trip went (from its title and destination), lit on the card's map.
        $bangladesh = $maps->get('bd');
        $places = $bangladesh ? array_slice($suggestions->match($trip, $bangladesh), 0, 4) : [];

        $days = $trip->starts_on && $trip->ends_on ? (int) $trip->starts_on->diffInDays($trip->ends_on) + 1 : null;
        $biggest = $trip->expenses()->reorder()->orderByDesc('amount')->first(['title', 'amount', 'category']);

        return view('trips.recap', [
            'trip' => $trip,
            'recap' => [
                'map' => $places !== [] ? $bangladesh : null,
                'places' => $places,
                'title_lines' => $this->lines($trip->title, 13, 3),
                'destination' => $trip->destination ? mb_strimwidth($trip->destination, 0, 40, '...') : null,
                'dates' => $trip->starts_on
                    ? $trip->starts_on->format('j M').($trip->ends_on && ! $trip->ends_on->isSameDay($trip->starts_on) ? ' - '.$trip->ends_on->format('j M Y') : ' '.$trip->starts_on->format('Y'))
                    : null,
                'days' => $days,
                'spent' => $stats['spent'],
                'per_person' => $stats['per_person'],
                'per_day' => $days && $stats['participants'] > 0 ? round($stats['spent'] / $stats['participants'] / $days) : null,
                'travellers' => $stats['travellers'],
                'costs' => $stats['expense_count'],
                'categories' => array_slice($summary['categories'], 0, 4),
                'biggest' => $biggest ? ['title' => mb_strimwidth($biggest->title, 0, 26, '...'), 'amount' => (float) $biggest->amount] : null,
                'settled' => $summary['suggestions'] === [],
            ],
        ]);
    }

    /**
     * Word-wrap for SVG text, which cannot wrap by itself.
     *
     * @return array<int, string>
     */
    private function lines(string $text, int $width, int $max): array
    {
        $lines = explode("\n", wordwrap(trim($text), $width, "\n", true));

        if (count($lines) > $max) {
            $lines = array_slice($lines, 0, $max);
            $lines[$max - 1] = mb_strimwidth($lines[$max - 1], 0, $width - 1, '').'...';
        }

        return $lines;
    }
}
