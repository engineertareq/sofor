<?php

namespace App\Http\Controllers;

use App\Enums\MemberStatus;
use App\Models\Trip;
use App\Models\TripMember;
use App\Support\Glyphs;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * People the signed in traveller has shared a trip with before, so a new trip
 * can be filled from them instead of retyping names and contact details.
 *
 * Only trips the traveller can already open are searched, so nothing shows up
 * here that the trip page itself would not have shown them.
 */
class PastTravellerController extends Controller
{
    private const LIMIT = 8;

    /** Rows read before folding, newest first. Plenty for the top few people. */
    private const SCAN = 300;

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $term = mb_substr(trim($request->string('q')->toString()), 0, 80);
        // Typed % and _ are searched for literally, not as LIKE wildcards.
        $like = '%'.addcslashes($term, '\\%_').'%';

        $rows = TripMember::query()
            ->whereIn('trip_id', Trip::visibleTo($user)->select('id'))
            ->whereIn('status', [MemberStatus::Active->value, MemberStatus::Invited->value])
            ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', '!=', $user->id))
            ->when($term !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'like', $like)
                ->orWhere('email', 'like', $like)))
            ->with(['trip:id,title,emoji', 'user:id,avatar_path'])
            ->latest('id')
            ->limit(self::SCAN)
            ->get()
            // A pending invite saved against the leader's own email or phone.
            ->reject(fn (TripMember $member) => ($member->email !== null && $member->email === $user->email)
                || ($member->phone !== null && $member->phone === $user->phone));

        return response()->json([
            'travellers' => array_slice($this->fold($rows), 0, self::LIMIT),
        ]);
    }

    /**
     * One entry per person. The same traveller has a row on every trip they
     * joined, matched here by account, email or phone - or by name alone for
     * travellers who never had contact details saved.
     *
     * @param  iterable<TripMember>  $rows  newest first
     * @return list<array<string, mixed>>
     */
    private function fold(iterable $rows): array
    {
        $people = [];
        $slots = [];

        foreach ($rows as $member) {
            $keys = array_values(array_filter([
                $member->user_id !== null ? 'u:'.$member->user_id : null,
                $member->email !== null ? 'e:'.$member->email : null,
                $member->phone !== null ? 'p:'.$member->phone : null,
            ]));

            if ($keys === []) {
                $keys = ['n:'.mb_strtolower(trim($member->name))];
            }

            $slot = null;

            foreach ($keys as $key) {
                if (isset($slots[$key])) {
                    $slot = $slots[$key];

                    break;
                }
            }

            if ($slot === null) {
                $slot = count($people);
                $people[$slot] = ['member' => $member, 'email' => null, 'phone' => null, 'trips' => []];
            }

            // The newest row names the person; older rows only fill in contact
            // details the newer one is missing.
            $people[$slot]['email'] ??= $member->email;
            $people[$slot]['phone'] ??= $member->phone;
            $people[$slot]['trips'][$member->trip_id] = true;

            foreach ($keys as $key) {
                $slots[$key] ??= $slot;
            }
        }

        return array_map(function (array $person) {
            /** @var TripMember $member */
            $member = $person['member'];

            return [
                'key' => 'm'.$member->id,
                'name' => $member->name,
                'email' => $person['email'],
                'phone' => $person['phone'],
                'contact' => $person['email'] ?? Phone::pretty($person['phone']) ?? __('Name only'),
                'initials' => $member->initials(),
                'gradient' => $member->avatarGradient(),
                'avatar' => $member->avatarUrl(),
                'registered' => $member->user_id !== null,
                'trips' => count($person['trips']),
                'last_trip' => $member->trip?->title,
                'last_icon' => Glyphs::svg($member->trip?->icon ?? 'compass', 'inline-block size-3.5 align-[-2px]'),
            ];
        }, $people);
    }
}
