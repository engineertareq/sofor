<?php

namespace App\Services;

use App\Models\Trip;
use App\Support\Phrase;

/**
 * Tells the group when spending passes 80% of the budget and again when it
 * goes over. Each alert goes out once; if spending drops back (a cost was
 * removed, or the budget was raised) the level drops with it, so crossing
 * the line again alerts again.
 */
class BudgetAlerts
{
    public const LEVELS = [80, 100];

    public function __construct(private readonly TripActivityLogger $logger) {}

    public function check(Trip $trip): void
    {
        $trip->refresh();

        $units = (float) $trip->participants()->sum('share_units');
        $total = $trip->budgetTotal(max(1, $units));
        $spent = round((float) $trip->expenses()->sum('amount'), 2);
        $used = $total ? $spent / $total * 100 : 0;

        $level = 0;

        foreach (self::LEVELS as $threshold) {
            if ($total && $used >= $threshold) {
                $level = $threshold;
            }
        }

        $previous = (int) $trip->budget_alert_level;

        if ($level === $previous) {
            return;
        }

        $trip->forceFill(['budget_alert_level' => $level])->saveQuietly();

        if ($level < $previous) {
            return;
        }

        // Amounts are written for each reader (Bangla digits for Bangla readers).
        $money = fn (float $value) => Phrase::money($value, $trip->currency);
        $perPerson = (float) $trip->budget_per_person > 0;

        [$title, $body, $tone] = $level >= 100
            ? [
                Phrase::make(':trip is over budget', ['trip' => $trip->title]),
                $perPerson
                    ? Phrase::make(':spent spent against :budget (:per_person a person). :over over so far.', [
                        'spent' => $money($spent), 'budget' => $money($total), 'per_person' => $money((float) $trip->budget_per_person), 'over' => $money($spent - $total),
                    ])
                    : Phrase::make(':spent spent against :budget. :over over so far.', [
                        'spent' => $money($spent), 'budget' => $money($total), 'over' => $money($spent - $total),
                    ]),
                'error',
            ]
            : [
                Phrase::make(':trip has used :percent% of its budget', ['trip' => $trip->title, 'percent' => (int) floor($used)]),
                Phrase::make(':spent of :budget spent - :left left.', ['spent' => $money($spent), 'budget' => $money($total), 'left' => $money($total - $spent)]),
                'warning',
            ];

        $this->logger->log($trip, 'budget_alert', $title, null, null, ['level' => $level, 'spent' => $spent, 'budget' => $total]);
        $this->logger->notifyMembers($trip, $title, $body, $tone, 'wallet');
    }
}
