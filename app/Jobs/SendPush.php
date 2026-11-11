<?php

namespace App\Jobs;

use App\Models\DeviceToken;
use App\Services\Fcm;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Pushes one alert to every phone a traveller is signed in on. Queued, so
 * adding a cost never waits on Google; the scheduler drains the queue every
 * minute (routes/console.php).
 */
class SendPush implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    /**
     * @param  array<string, scalar|null>  $data
     */
    public function __construct(
        public readonly int $userId,
        public readonly string $title,
        public readonly string $body,
        public readonly array $data = [],
        public readonly string $channel = 'trips',
    ) {}

    public function handle(Fcm $fcm): void
    {
        $tokens = DeviceToken::where('user_id', $this->userId)->pluck('token')->all();

        foreach ($fcm->send($tokens, $this->title, $this->body, $this->data, $this->channel) as $gone) {
            DeviceToken::forget($gone);
        }
    }
}
