<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ExplorerRanking;
use App\Services\TravelMaps;
use App\Support\ExplorerLevel;
use App\Support\TravelMap;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * /explorers: the public leaderboard of travellers who have seen the most,
 * one board per travel map (Bangladesh first, then the world).
 */
class ExplorerController extends Controller
{
    private const PER_PAGE = 20;

    public function __construct(
        private readonly TravelMaps $maps,
        private readonly ExplorerRanking $ranking,
    ) {}

    public function index(Request $request): View
    {
        $keys = $this->maps->keys();
        $key = in_array($request->query('map'), $keys, true) ? $request->query('map') : $keys[0];
        $map = $this->maps->get($key);

        $standings = $this->ranking->standings($map);
        $search = mb_substr(trim($request->string('q')->toString()), 0, 60);
        $page = max(1, LengthAwarePaginator::resolveCurrentPage());

        // The top three stand on the podium; the list carries on from fourth.
        // A search lists matches with their real overall rank instead.
        $podium = $search === '' ? $standings->take(3)->values() : collect();
        $pool = $search === '' ? $standings->slice($podium->count())->values() : $this->matching($standings, $search);

        $rows = new LengthAwarePaginator(
            $pool->forPage($page, self::PER_PAGE)->values(),
            $pool->count(),
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        if ($page > 1) {
            $podium = collect();
        }

        $shownIds = $podium->pluck('user_id')->merge($rows->getCollection()->pluck('user_id'))->unique()->values()->all();
        $popularity = $this->ranking->popularity($map);

        return view('explorers.index', [
            'map' => $map,
            'tabs' => collect($keys)->map(fn (string $tabKey) => [
                'key' => $tabKey,
                'label' => $tabKey === config('travel_maps.world') ? __('Worldwide') : $this->maps->get($tabKey)->label(),
                'explorers' => $tabKey === $key ? $standings->count() : $this->ranking->count($this->maps->get($tabKey)),
            ])->all(),
            'search' => $search,
            'podium' => $podium,
            'rows' => $rows,
            'users' => User::query()->whereIn('id', $shownIds)->get()->keyBy('id'),
            'groups' => $this->ranking->groupsVisited($map, $shownIds),
            'me' => $this->viewer($request->user(), $map, $standings),
            'popularity' => $popularity,
            'levels' => ExplorerLevel::all($map->countedTotal()),
            'levelCounts' => ExplorerLevel::tally($standings, $map->countedTotal()),
            // Counted places nobody has marked yet: an open invitation.
            'unexplored' => collect($map->areas())->keys()
                ->filter(fn (string $id) => $map->isCounted($id) && ! isset($popularity[$id]))
                ->values()
                ->all(),
            'summary' => [
                'explorers' => $standings->count(),
                'best' => $standings->first()['visited'] ?? 0,
                'covered' => count($popularity),
                'total' => $map->countedTotal(),
            ],
        ]);
    }

    /**
     * Search by name or username, keeping everybody's overall rank.
     *
     * @param  Collection<int, array{rank: int, user_id: int, visited: int, reached_at: string}>  $standings
     * @return Collection<int, array{rank: int, user_id: int, visited: int, reached_at: string}>
     */
    private function matching(Collection $standings, string $search): Collection
    {
        $term = '%'.ltrim($search, '@').'%';

        $ids = User::query()
            ->whereIn('id', $standings->pluck('user_id'))
            ->where(fn ($query) => $query->where('name', 'like', $term)->orWhere('username', 'like', $term))
            ->pluck('id')
            ->flip();

        return $standings->filter(fn (array $row) => isset($ids[$row['user_id']]))->values();
    }

    /**
     * Where the person looking stands, and what would move them up.
     *
     * @param  Collection<int, array{rank: int, user_id: int, visited: int, reached_at: string}>  $standings
     * @return array<string, mixed>
     */
    private function viewer(?User $user, TravelMap $map, Collection $standings): array
    {
        if ($user === null) {
            return ['state' => 'guest'];
        }

        if ($user->isAdmin()) {
            return ['state' => 'admin'];
        }

        if (! $user->profile_public) {
            return ['state' => 'private'];
        }

        $entry = $standings->firstWhere('user_id', $user->id);

        if ($entry === null) {
            return ['state' => 'unranked'];
        }

        $ahead = $entry['rank'] > 1 ? $standings[$entry['rank'] - 2] : null;
        $leader = $standings->first();

        return [
            'state' => 'ranked',
            'entry' => $entry,
            'explorers' => $standings->count(),
            // Matching them is not enough: ties go to whoever got there first.
            'needed' => $ahead !== null ? $ahead['visited'] - $entry['visited'] + 1 : 0,
            'ahead' => $ahead !== null ? User::find($ahead['user_id']) : null,
            'aheadVisited' => $ahead['visited'] ?? null,
            'leader' => $leader !== null && $leader['user_id'] !== $user->id ? User::find($leader['user_id']) : null,
            'leaderVisited' => $leader['visited'] ?? 0,
            'total' => $map->countedTotal(),
        ];
    }
}
