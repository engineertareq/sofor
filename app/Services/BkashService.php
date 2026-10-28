<?php

namespace App\Services;

use App\Exceptions\BkashException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper around the bKash Checkout (PGW) API.
 *
 * Flow: grant a token -> create a payment -> the traveller approves it on the
 * bKash page -> execute the payment on the way back -> the ledger records the
 * contribution. Everything is guarded so a failed gateway call can never leave
 * a half written payment in the trip.
 */
class BkashService
{
    /**
     * Which Checkout API generation a merchant account is on. bKash issues
     * `checkout.*.bka.sh` hosts for the legacy (non tokenized) API and
     * `tokenized.*.bka.sh` hosts for the newer one, so the host gives it away.
     */
    public static function detectGeneration(?string $baseUrl, ?string $api = 'auto'): string
    {
        $api = strtolower((string) $api);

        if (in_array($api, ['legacy', 'tokenized'], true)) {
            return $api;
        }

        return str_contains((string) $baseUrl, 'tokenized.') ? 'tokenized' : 'legacy';
    }

    /**
     * Endpoint paths for a generation, relative to the configured prefix.
     *
     * @return array<string, string>
     */
    public static function pathsFor(string $generation): array
    {
        // Query is "Query Payment": GET /checkout/payment/query/{paymentID} on the
        // legacy API, POST /tokenized/checkout/payment/status on the tokenized one.
        return $generation === 'legacy' ? [
            'token' => '/token/grant',
            'create' => '/payment/create',
            'execute' => '/payment/execute/',
            'query' => '/payment/query/',
            'refund' => '/payment/refund',
        ] : [
            'token' => '/token/grant',
            'create' => '/create',
            'execute' => '/execute',
            'query' => '/payment/status',
            'refund' => '/payment/refund',
        ];
    }

    /**
     * Whether bKash says the money moved. Execute and Query Payment both answer
     * transactionStatus "Completed" for a paid payment ("Initiated" before the
     * payer approves it, "Authorized" between approval and execute); when they
     * send a statusCode it is "0000". There is no "Success" status.
     *
     * @param  array<string, mixed>  $result
     */
    public static function isCompleted(array $result): bool
    {
        $status = strtolower(trim((string) ($result['transactionStatus'] ?? '')));
        $code = $result['statusCode'] ?? null;

        return $status === 'completed' && ($code === null || (string) $code === '0000');
    }

    public function __construct(
        private readonly ?string $baseUrl = null,
        private readonly ?string $username = null,
        private readonly ?string $password = null,
        private readonly ?string $appKey = null,
        private readonly ?string $appSecret = null,
        private readonly float $mfsChargePercent = 1.5,
        private readonly int $timeout = 30,
        private readonly bool $sandbox = true,
        private readonly string $pathPrefix = '/tokenized/checkout',
        private readonly array $paths = [
            'token' => '/token/grant',
            'create' => '/create',
            'execute' => '/execute',
            'query' => '/payment/query',
            'refund' => '/payment/refund',
        ],
        private readonly bool $idInPath = false,
        private readonly ?string $scriptUrl = null,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            baseUrl: config('bkash.base_url'),
            username: config('bkash.username'),
            password: config('bkash.password'),
            appKey: config('bkash.app_key'),
            appSecret: config('bkash.app_secret'),
            mfsChargePercent: (float) config('bkash.mfs_charge_percent', 1.5),
            timeout: (int) config('bkash.timeout', 30),
            sandbox: (bool) config('bkash.sandbox', true),
            pathPrefix: (string) config('bkash.path_prefix', '/tokenized/checkout'),
            paths: (array) config('bkash.paths', []),
            idInPath: (bool) config('bkash.id_in_path', false),
            scriptUrl: config('bkash.script_url'),
        );
    }

    /** 'legacy' or 'tokenized' - whichever generation the config resolved to. */
    public function apiGeneration(): string
    {
        return $this->idInPath ? 'legacy' : 'tokenized';
    }

    /**
     * True when the hosted page is rendered by bKash's Checkout JS instead of
     * being reached through a `bkashURL` redirect. That is the legacy flow, and
     * it is why those accounts get a ``Checkout js link`` from bKash.
     */
    public function usesBrowserCheckout(): bool
    {
        return (string) config('bkash.mode', 'redirect') === 'browser' && filled($this->scriptUrl);
    }

    /**
     * Credentials are present, so the API can be called.
     */
    public function isConfigured(): bool
    {
        // Empty strings must count as missing: a half filled .env used to look
        // like a working setup and the payment only failed once a user tried it.
        return filled($this->baseUrl)
            && filled($this->username)
            && filled($this->password)
            && filled($this->appKey)
            && filled($this->appSecret);
    }

    /**
     * Sofor can actually collect a payment: usable credentials plus, when the
     * hosted page has to come from a URL, somewhere to send the traveller.
     */
    public function canTakePayments(): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        // The browser flow gets its page from bKash's Checkout JS, so a legacy
        // account needs nothing else; a redirect flow needs a page to redirect to.
        return $this->usesBrowserCheckout()
            || ! $this->idInPath
            || trim((string) config('bkash.checkout_url', '')) !== '';
    }

    public function mfsChargePercent(): float
    {
        return $this->mfsChargePercent;
    }

    /**
     * Splits an amount into the share that clears the traveller's due and the
     * MFS charge that bKash adds on top of it.
     *
     * @return array{base: float, percent: float, charge: float, total: float}
     */
    /**
     * Whether an executed payment is for the amount Sofor asked for. The
     * amount is fixed when the payment is created, so a mismatch means the
     * result does not belong to this checkout and must not be recorded.
     *
     * @param  array<string, mixed>  $result  bKash's execute response
     */
    public function confirmsAmount(array $result, float $expected): bool
    {
        // Older gateway versions leave the amount out; the status and trxID checks still apply.
        if (! isset($result['amount']) || ! is_numeric($result['amount'])) {
            return true;
        }

        return abs((float) $result['amount'] - round($expected, 2)) < 0.01;
    }

    public function breakdown(float $base): array
    {
        $base = round($base, 2);
        $charge = round($base * $this->mfsChargePercent / 100, 2);
        $total = round($base + $charge, 2);

        if ($this->sandbox) {
            // The sandbox rejects fractional taka, so round up to be safe.
            $total = ceil($total);
            $charge = round($total - $base, 2);
        }

        return [
            'base' => $base,
            'percent' => $this->mfsChargePercent,
            'charge' => $charge,
            'total' => $total,
        ];
    }

    /**
     * What to actually ask bKash for when the gateway fee is NOT passed on to
     * the payer. Sofor absorbs the fee itself, so the payer is charged exactly
     * what they chose. The sandbox refuses fractional taka, so it rounds up
     * there and nowhere else.
     */
    public function payableAmount(float $amount): float
    {
        return $this->sandbox ? ceil($amount) : round($amount, 2);
    }

    /**
     * @return array<string, mixed>
     */
    public function accessToken(bool $refresh = false): string
    {
        $this->guardConfigured();

        if ($refresh) {
            Cache::forget('bkash:token');
        }

        $cached = Cache::get('bkash:token');

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = $this->client()
            ->withHeaders([
                'username' => $this->username,
                'password' => $this->password,
                'app_key' => $this->appKey,
                'app_secret' => $this->appSecret,
            ])
            ->post($this->url($this->path('token')), [
                'app_key' => $this->appKey,
                'app_secret' => $this->appSecret,
            ]);

        $response = $this->payload($response);

        $token = $response['id_token'] ?? null;

        if (! is_string($token) || $token === '') {
            throw BkashException::fromResponse('token grant', (array) $response);
        }

        Cache::put('bkash:token', $token, now()->addMinutes(50));

        return $token;
    }

    /**
     * @return array<string, mixed>
     */
    public function createPayment(float $amount, string $invoiceNumber, string $callbackUrl, string $payerReference = ''): array
    {
        $this->guardConfigured();

        $payload = [
            'amount' => number_format($amount, 2, '.', ''),
            'currency' => config('bkash.currency', 'BDT'),
            'intent' => 'sale',
            'merchantInvoiceNumber' => $invoiceNumber,
            'callbackURL' => $callbackUrl,
            'payerReference' => $payerReference,
        ];

        $response = $this->payload($this->authorised()->post($this->url($this->path('create')), $payload));

        if (! isset($response['paymentID'])) {
            throw BkashException::fromResponse('checkout create', (array) $response);
        }

        // The tokenized API hands back the page to send the traveller to. The
        // legacy API does not: bKash's Checkout JS renders that page in the
        // browser, so there is nothing to resolve here.
        $checkoutUrl = $response['bkashURL'] ?? $this->checkoutUrl((string) $response['paymentID']);

        if (is_string($checkoutUrl) && $checkoutUrl !== '') {
            $response['bkashURL'] = $checkoutUrl;
        } elseif (! $this->usesBrowserCheckout()) {
            throw new BkashException(
                'checkout create: bKash created payment '.$response['paymentID'].' but did not return a payment page. '
                .'The legacy Checkout API never returns one - either let bKash\'s Checkout JS render it (BKASH_MODE=browser) '
                .'or set BKASH_CHECKOUT_URL to the Checkout URL from your bKash merchant portal.'
            );
        }

        return $response;
    }

    /**
     * The page the traveller is sent to. Returns null when it cannot be built,
     * which is the case for a legacy account without BKASH_CHECKOUT_URL.
     */
    private function checkoutUrl(string $paymentId): ?string
    {
        $configured = trim((string) config('bkash.checkout_url', ''));

        if ($configured === '') {
            return null;
        }

        return $configured.(str_contains($configured, '?') ? '&' : '?').'paymentID='.urlencode($paymentId);
    }

    /**
     * @return array<string, mixed>
     */
    public function executePayment(string $paymentId): array
    {
        $this->guardConfigured();

        // Legacy: POST /checkout/payment/execute/{paymentID}. Tokenized: POST /execute with the id in the body.
        $response = $this->payload($this->idInPath
            ? $this->authorised()->post($this->idUrl('execute', $paymentId))
            : $this->authorised()->post($this->url($this->path('execute')), ['paymentID' => $paymentId]));

        if (($response['transactionStatus'] ?? null) === null && (($response['statusCode'] ?? '') !== '0000')) {
            throw BkashException::fromResponse('checkout execute', (array) $response);
        }

        return $response;
    }

    /**
     * Query Payment: where a payment stands right now, by its payment id.
     *
     * @return array<string, mixed>
     */
    public function queryPayment(string $paymentId): array
    {
        $this->guardConfigured();

        return $this->payload($this->idInPath
            ? $this->authorised()->get($this->idUrl('query', $paymentId))
            : $this->authorised()->post($this->url($this->path('query')), ['paymentID' => $paymentId]));
    }

    /**
     * Execute a payment the payer has approved, and say whether the money moved.
     *
     * Execute can fail or come back unclear while the money has in fact moved:
     * a timeout, a second tap ("already executed"), a dropped connection. So
     * whenever execute does not say Completed, Query Payment is asked before
     * anything is called failed.
     *
     * @return array{completed: bool, result: array<string, mixed>}
     */
    public function settle(string $paymentId): array
    {
        $result = [];

        try {
            $result = $this->executePayment($paymentId);

            if (self::isCompleted($result)) {
                return ['completed' => true, 'result' => $result];
            }
        } catch (\Throwable $exception) {
            $result = ['error' => $exception->getMessage()];
        }

        try {
            $status = $this->queryPayment($paymentId);
        } catch (\Throwable $exception) {
            Log::warning('bKash query after an unclear execute failed', ['paymentID' => $paymentId, 'execute' => $result, 'error' => $exception->getMessage()]);

            return ['completed' => false, 'result' => $result];
        }

        if (self::isCompleted($status)) {
            Log::info('bKash execute was unclear but the payment is completed', ['paymentID' => $paymentId, 'execute' => $result]);

            return ['completed' => true, 'result' => $status];
        }

        Log::warning('bKash payment not completed', ['paymentID' => $paymentId, 'execute' => $result, 'query' => $status]);

        return ['completed' => false, 'result' => $status + $result];
    }

    /**
     * @return array<string, mixed>
     */
    public function refundPayment(string $paymentId, float $amount, string $trxId, string $reason): array
    {
        $this->guardConfigured();

        return $this->payload($this->authorised()
            ->post($this->url($this->path('refund')), [
                'paymentID' => $paymentId,
                'amount' => number_format($amount, 2, '.', ''),
                'trxID' => $trxId,
                'reason' => $reason,
            ]));
    }

    private function client(): PendingRequest
    {
        return Http::withHeaders(['Content-Type' => 'application/json'])
            ->acceptJson()
            ->timeout($this->timeout)
            ->retry(2, 250, throw: false);
    }

    private function authorised(): PendingRequest
    {
        return $this->client()
            ->withHeaders([
                'X-APP-Key' => $this->appKey,
                'Authorization' => $this->accessToken(),
            ]);
    }

    private function url(string $path): string
    {
        $prefix = trim((string) $this->pathPrefix, '/');

        return rtrim((string) $this->baseUrl, '/')
            .($prefix !== '' ? '/'.$prefix : '')
            .$path;
    }

    /** The path for a named endpoint, e.g. `token`, `create`, `execute`. */
    private function path(string $key): string
    {
        return (string) ($this->paths[$key] ?? '');
    }

    /** The legacy API takes the payment id inside the path: /checkout/payment/execute/{paymentID}. */
    private function idUrl(string $key, string $paymentId): string
    {
        return $this->url(rtrim($this->path($key), '/').'/'.urlencode($paymentId));
    }

    /**
     * bKash replies with JSON, but some of its messages embed raw control
     * characters that break strict decoding. Falling back to the raw body keeps
     * the gateway's actual reason available for logs and local debugging.
     *
     * @return array<string, mixed>
     */
    private function payload(\Illuminate\Http\Client\Response $response): array
    {
        $json = $response->json();

        if (is_array($json)) {
            return $json;
        }

        $body = (string) $response->body();

        if (preg_match('/"(statusMessage|errorMessage|message)"\s*:\s*"([^"]+)"/s', $body, $matches) === 1) {
            return [$matches[1] => trim(preg_replace('/\s+/', ' ', $matches[2]) ?? '')];
        }

        return ['message' => trim(preg_replace('/\s+/', ' ', strip_tags($body)) ?? '')];
    }

    private function guardConfigured(): void
    {
        if (! $this->isConfigured()) {
            Log::warning('bKash credentials are not configured, the gateway call was skipped.');

            throw new BkashException('bKash is not configured yet.');
        }
    }
}
