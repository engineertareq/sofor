<?php

namespace App\Console\Commands;

use App\Models\Donation;
use App\Models\SmsTopup;
use App\Services\BkashService;
use App\Services\BkashSettlement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Asks bKash about SMS top-ups and gifts Sofor marked failed or left pending,
 * and books the ones bKash says were paid - the same checks as a live payment
 * (BkashSettlement): the amount asked for, a transaction id not seen before,
 * credited once. Read-only towards bKash: it never executes or refunds.
 *
 * Runs hourly (routes/console.php); run it by hand to recover a payment now:
 *   php artisan bkash:reconcile --dry-run    see what bKash says, change nothing
 *   php artisan bkash:reconcile              credit what was paid
 */
class BkashReconcileCommand extends Command
{
    protected $signature = 'bkash:reconcile
        {--days=14 : How far back to look}
        {--minutes=15 : Leave payments younger than this alone, the payer may still be paying}
        {--dry-run : Only show what bKash says, change nothing}';

    protected $description = 'Credit SMS top-ups and gifts that bKash took but Sofor marked failed or pending';

    public function handle(BkashService $bkash, BkashSettlement $settlement): int
    {
        if (! $bkash->isConfigured()) {
            $this->warn('bKash is not configured, nothing to check.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $window = [now()->subDays(max(1, (int) $this->option('days'))), now()->subMinutes(max(0, (int) $this->option('minutes')))];
        $rows = [];
        $booked = 0;

        foreach (['top-up' => SmsTopup::class, 'gift' => Donation::class] as $kind => $model) {
            $records = $model::query()
                ->whereIn('status', ['failed', 'pending'])
                ->whereNotNull('payment_id')
                ->whereBetween('created_at', $window)
                ->orderBy('id')
                ->get();

            foreach ($records as $record) {
                $before = $record->status;

                try {
                    $status = $bkash->queryPayment((string) $record->payment_id);
                } catch (\Throwable $exception) {
                    $rows[] = [$kind, $record->id, $record->payment_id, $record->amount, $before, 'query failed', $exception->getMessage()];

                    continue;
                }

                $says = (string) ($status['transactionStatus'] ?? $status['statusMessage'] ?? $status['errorMessage'] ?? $status['message'] ?? '?');
                $action = 'left '.$before;

                if (BkashService::isCompleted($status)) {
                    if ($dryRun) {
                        $action = 'would book';
                    } else {
                        $outcome = $record instanceof SmsTopup ? $settlement->topup($record, $status) : $settlement->donation($record, $status);
                        $action = $outcome['ok'] ? 'booked' : 'refused: '.$outcome['message'];

                        if ($outcome['ok']) {
                            $booked++;
                            Log::info('bKash reconcile booked a '.$kind, ['id' => $record->id, 'paymentID' => $record->payment_id, 'trxID' => $status['trxID'] ?? null]);
                        }
                    }
                }

                $rows[] = [$kind, $record->id, $record->payment_id, $record->amount, $before, $says.(isset($status['trxID']) ? ' ('.$status['trxID'].')' : ''), $action];
            }
        }

        if ($rows === []) {
            $this->info('Nothing failed or pending in that window.');

            return self::SUCCESS;
        }

        $this->table(['What', 'ID', 'bKash payment', 'Amount', 'Sofor had', 'bKash says', 'Done'], $rows);
        $this->info($dryRun ? 'Dry run: nothing was changed.' : "Booked {$booked}.");

        return self::SUCCESS;
    }
}
