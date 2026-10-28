<?php

namespace App\Console\Commands;

use App\Services\BkashService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Tells you, in one command, whether the bKash credentials in .env actually work
 * and which API generation the merchant account is on. It only calls the token
 * grant endpoint - no payment is ever created by this command.
 */
class BkashCheckCommand extends Command
{
    protected $signature = 'bkash:check
        {--create : Also create a real payment intent and query its status (no money moves)}
        {--amount=100 : Amount for the test payment intent, in taka}';

    protected $description = 'Verify the bKash credentials, detect the API generation and optionally test the handshake';

    public function handle(): int
    {
        $service = BkashService::fromConfig();

        $this->newLine();
        $this->line('  <fg=gray>Base URL</>      <options=bold>'.(config('bkash.base_url') ?: '(not set)').'</>');
        $this->line('  <fg=gray>API</>           <options=bold>'.config('bkash.api').'</>  <fg=gray>(resolved to "'.$service->apiGeneration().'")</>');
        $this->line('  <fg=gray>Checkout</>      <options=bold>'.config('bkash.mode').'</>  <fg=gray>'.($service->usesBrowserCheckout()
            ? '(bKash Checkout JS renders the page in the browser)'
            : '(the traveller is redirected to the payment page)').'</>');
        $this->line('  <fg=gray>Path prefix</>   <options=bold>'.(config('bkash.path_prefix') ?: '(none)').'</>');
        $this->line('  <fg=gray>Mode</>          <options=bold>'.(config('bkash.sandbox') ? 'sandbox' : 'live / production').'</>');
        $this->line('  <fg=gray>MFS charge</>    <options=bold>'.config('bkash.mfs_charge_percent').'%</>');
        $this->line('  <fg=gray>Credentials</>   '.$this->credentialSummary());

        $checkoutUrl = trim((string) config('bkash.checkout_url', ''));
        $this->line('  <fg=gray>Checkout URL</>  '.($checkoutUrl !== ''
            ? '<fg=green>set</> <fg=gray>('.$checkoutUrl.')</>'
            : ($service->usesBrowserCheckout()
                ? '<fg=gray>not needed - the Checkout JS renders the page</>'
                : ($service->apiGeneration() === 'legacy'
                    ? '<fg=yellow>not set</> <fg=gray>- the legacy API does not return one</>'
                    : '<fg=gray>not needed (bKash returns bkashURL with every payment)</>'))));

        $this->newLine();

        if (! $service->isConfigured()) {
            $this->error('  bKash is not configured, so the payment option stays hidden in the app.');
            $this->line('  Fill BKASH_USERNAME, BKASH_PASSWORD, BKASH_APP_KEY and BKASH_APP_SECRET in .env.');
            $this->newLine();

            return self::FAILURE;
        }

        $candidates = $this->candidates();
        $working = null;

        foreach ($candidates as $candidate) {
            [$label, $base, $prefix] = $candidate;

            $this->line('  <fg=gray>Testing</> '.$label);
            $result = $this->probe($base, $prefix);

            if ($result['ok']) {
                $this->line('    <fg=green;options=bold>OK</> - access token received ('.$result['note'].')');
                $working ??= $candidate;

                continue;
            }

            $this->line('    <fg=red>FAILED</> - '.$result['note']);
        }

        $this->newLine();

        if ($working === null) {
            $this->error('  No endpoint answered with an access token.');
            $this->line('  That means the credentials themselves were rejected, not the paths:');
            $this->line('  copy them again from the bKash merchant portal (Merchant -> Tokens / Credentials)');
            $this->line('  and rotate them if the portal shows a rotation date in the past.');
            $this->newLine();

            return self::FAILURE;
        }

        [$label, $base, $prefix] = $working;
        $generation = str_contains($prefix, 'tokenized') ? 'tokenized' : 'legacy';

        $this->info('  bKash is working with: '.$label);
        $this->newLine();
        $this->line('  Put this in .env:');
        $this->line('    <options=bold>BKASH_BASE_URL='.$base.'</>');
        $this->line('    <options=bold>BKASH_API='.$generation.'</>');
        $this->line('    <options=bold>BKASH_SANDBOX='.(str_contains($base, 'sandbox') ? 'true' : 'false').'</>');

        if ($base !== rtrim((string) config('bkash.base_url'), '/') || $generation !== $service->apiGeneration()) {
            $this->newLine();
            $this->warn('  The .env values currently point somewhere else, so update them.');
        }

        $this->newLine();

        if ($this->option('create')) {
            return $this->handshake($base, $prefix);
        }

        return self::SUCCESS;
    }

    /**
     * Creates a real payment intent and reads its status back. Nothing is charged
     * until somebody approves the payment on the bKash page, and an intent that is
     * never approved simply expires on bKash's side.
     */
    private function handshake(string $base, string $prefix): int
    {
        $amount = (float) $this->option('amount');
        $service = BkashService::fromConfig();

        $this->line('  <fg=gray>Creating a test payment intent for</> <options=bold>'.\App\Support\Money::format($amount).'</>');

        try {
            $payment = $service->createPayment(
                amount: $amount,
                invoiceNumber: 'SOFOR-CHECK-'.now()->format('ymdHis'),
                callbackUrl: route('support.callback'),
                payerReference: 'Sofor credential check',
            );
        } catch (\Throwable $exception) {
            $this->error('  Could not create a payment: '.$exception->getMessage());

            if (str_contains($exception->getMessage(), 'BKASH_CHECKOUT_URL')) {
                $this->newLine();
                $this->warn('  The API calls themselves work. What is missing is the hosted payment page:');
                $this->line('  the legacy Checkout API does not return one with the payment, so either');
                $this->line('  let bKash\'s Checkout JS render it (the default for legacy accounts):');
                $this->newLine();
                $this->line('    <options=bold>BKASH_MODE=browser</>');
                $this->newLine();
                $this->line('  or paste the Checkout URL from the bKash merchant portal:');
                $this->newLine();
                $this->line('    <options=bold>BKASH_CHECKOUT_URL=https://... (the URL bKash gave you)</>');
            }

            $this->newLine();

            return self::FAILURE;
        }

        $this->line('    <fg=green;options=bold>OK</> - paymentID <options=bold>'.$payment['paymentID'].'</>');
        $this->line('    Checkout page: '.$payment['bkashURL']);
        $this->newLine();

        $this->line('  <fg=gray>Asking bKash for that payment\'s status</>');

        try {
            $status = $service->queryPayment((string) $payment['paymentID']);
            $this->line('    <fg=green;options=bold>OK</> - '.(string) ($status['transactionStatus'] ?? $status['statusMessage'] ?? json_encode($status)));
        } catch (\Throwable $exception) {
            // Some legacy accounts only expose the status endpoint after a payment
            // completes, so this is a note rather than a failure.
            $this->warn('    Status lookup did not answer: '.$exception->getMessage());
        }

        $this->newLine();
        $this->info('  The gateway handshake works. No money was taken: the intent stays unpaid until somebody approves it.');

        return self::SUCCESS;
    }

    /**
     * Every combination we know bKash answers on.
     *
     * @return array<int, array{0: string, 1: string, 2: string}>
     */
    private function candidates(): array
    {
        $configured = rtrim((string) config('bkash.base_url'), '/');
        $prefix = (string) config('bkash.path_prefix');

        $candidates = [[
            'configured endpoint ('.$configured.$prefix.')',
            $configured,
            $prefix,
        ]];

        $others = [
            ['legacy live (checkout.pay.bka.sh)', 'https://checkout.pay.bka.sh/v1.2.0-beta', '/checkout'],
            ['legacy sandbox (checkout.sandbox.bka.sh)', 'https://checkout.sandbox.bka.sh/v1.2.0-beta', '/checkout'],
            ['tokenized live (tokenized.pay.bka.sh)', 'https://tokenized.pay.bka.sh/v1.2.0-beta', '/tokenized/checkout'],
            ['tokenized sandbox (tokenized.sandbox.bka.sh)', 'https://tokenized.sandbox.bka.sh/v1.2.0-beta', '/tokenized/checkout'],
        ];

        foreach ($others as $other) {
            if ($other[1].$other[2] === $configured.$prefix) {
                continue;
            }

            $candidates[] = $other;
        }

        return $candidates;
    }

    /**
     * @return array{ok: bool, note: string}
     */
    private function probe(string $base, string $prefix): array
    {
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'username' => (string) config('bkash.username'),
                'password' => (string) config('bkash.password'),
                'app_key' => (string) config('bkash.app_key'),
                'app_secret' => (string) config('bkash.app_secret'),
            ])
                ->acceptJson()
                ->timeout((int) config('bkash.timeout', 30))
                ->post(rtrim($base, '/').rtrim($prefix, '/').'/token/grant', [
                    'app_key' => (string) config('bkash.app_key'),
                    'app_secret' => (string) config('bkash.app_secret'),
                ]);
        } catch (\Throwable $exception) {
            return ['ok' => false, 'note' => 'could not reach the host ('.$exception->getMessage().')'];
        }

        $body = (string) $response->body();
        $json = $response->json();
        $token = is_array($json) ? ($json['id_token'] ?? null) : null;

        if (is_string($token) && $token !== '') {
            return ['ok' => true, 'note' => 'HTTP '.$response->status()];
        }

        $message = is_array($json)
            ? ($json['statusMessage'] ?? $json['errorMessage'] ?? $json['message'] ?? null)
            : null;

        if (! is_string($message) || $message === '') {
            $message = preg_match('/"(statusMessage|errorMessage|message)"\s*:\s*"([^"]+)"/s', $body, $matches) === 1
                ? $matches[2]
                : trim(strip_tags($body));
        }

        $message = trim(preg_replace('/\s+/', ' ', $message) ?? '');

        return [
            'ok' => false,
            'note' => 'HTTP '.$response->status().($message !== '' ? ' - '.$message : ''),
        ];
    }

    private function credentialSummary(): string
    {
        $keys = ['username', 'password', 'app_key', 'app_secret'];
        $filled = collect($keys)
            ->filter(fn (string $key) => filled(config('bkash.'.$key)))
            ->count();

        return $filled === count($keys)
            ? '<fg=green>all 4 set</> <fg=gray>(values hidden)</>'
            : '<fg=red>'.(count($keys) - $filled).' missing</>';
    }
}
