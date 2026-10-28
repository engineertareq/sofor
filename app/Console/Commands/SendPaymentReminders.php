<?php

namespace App\Console\Commands;

use App\Models\PaymentReminder;
use App\Models\Trip;
use App\Services\PaymentReminders;
use Illuminate\Console\Command;

/**
 * The automatic "you still owe" reminders, run daily by the scheduler. After a
 * trip ends, whoever still owes is reminded two days later, then weekly, at
 * most PaymentReminders::AUTOMATIC_MAX times - in the app and by email, and
 * by text too if the leader switched that on (paid from the leader's wallet).
 */
class SendPaymentReminders extends Command
{
    protected $signature = 'sofor:remind-dues {--dry-run : Only list who would be reminded}';

    protected $description = 'Remind travellers who still owe money on ended trips';

    public function handle(PaymentReminders $reminders): int
    {
        $sent = 0;

        Trip::query()
            ->ended()
            ->where('ended_at', '<=', now()->subDays(2))
            ->with('owner')
            ->chunkById(50, function ($trips) use ($reminders, &$sent) {
                foreach ($trips as $trip) {
                    foreach ($trip->members()->with('user')->get() as $member) {
                        $automatic = PaymentReminder::query()->where('trip_member_id', $member->id)->where('automatic', true);
                        $count = (clone $automatic)->count();
                        $last = (clone $automatic)->latest('id')->value('created_at');

                        $due = $count === 0 || ($count < PaymentReminders::AUTOMATIC_MAX && $last !== null && now()->subDays(7)->greaterThanOrEqualTo($last));

                        if (! $due || $reminders->owes($trip, $member) === []) {
                            continue;
                        }

                        if ($this->option('dry-run')) {
                            $this->line("would remind {$member->name} on {$trip->title}");
                            $sent++;

                            continue;
                        }

                        $result = $reminders->remind($trip, $member, null, sms: $trip->sms_reminders, automatic: true, payer: $trip->owner);

                        if ($result['ok']) {
                            $sent++;
                        }
                    }
                }
            });

        $this->info(($this->option('dry-run') ? 'Would send ' : 'Sent ').$sent.' reminder'.($sent === 1 ? '' : 's').'.');

        return self::SUCCESS;
    }
}
