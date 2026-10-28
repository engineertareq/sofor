<?php

namespace App\Console\Commands;

use App\Http\Controllers\TripManifestController;
use App\Models\TravellerDetail;
use App\Models\Trip;
use Illuminate\Console\Command;

/**
 * ID numbers, birthdays and addresses are only needed while travelling. Run
 * daily: KEEP_DAYS after a trip ends, its checkpoint list is deleted.
 */
class PurgeCheckpointLists extends Command
{
    protected $signature = 'sofor:purge-checkpoint-lists';

    protected $description = 'Delete checkpoint-list details of trips that ended a while ago';

    public function handle(): int
    {
        $trips = Trip::query()
            ->ended()
            ->where('ended_at', '<=', now()->subDays(TripManifestController::KEEP_DAYS))
            ->where(fn ($query) => $query
                ->whereNotNull('checkpoint')
                ->orWhereHas('members', fn ($members) => $members->has('travellerDetail')));

        $people = 0;
        $count = 0;

        $trips->chunkById(100, function ($chunk) use (&$people, &$count) {
            foreach ($chunk as $trip) {
                $people += TravellerDetail::query()
                    ->whereIn('trip_member_id', $trip->members()->select('id'))
                    ->delete();

                $trip->forceFill(['checkpoint' => null])->saveQuietly();
                $count++;
            }
        });

        $this->info("Cleared {$people} traveller ".($people === 1 ? 'record' : 'records')." from {$count} ".($count === 1 ? 'trip' : 'trips').'.');

        return self::SUCCESS;
    }
}
