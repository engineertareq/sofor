<?php

namespace Tests\Concerns;

/**
 * bKash ships two Checkout (PGW) API generations. Both are supported in code, so
 * the tests configure the one they exercise instead of relying on the .env file.
 */
trait ConfiguresBkash
{
    protected function configureBkash(string $generation = 'tokenized', bool $sandbox = false, ?string $checkoutUrl = null): void
    {
        $legacy = $generation === 'legacy';

        config()->set('bkash.api', $generation);
        config()->set('bkash.mode', $legacy ? 'browser' : 'redirect');
        config()->set('bkash.base_url', $legacy
            ? 'https://checkout.pay.bka.sh/v1.2.0-beta'
            : 'https://tokenized.pay.bka.sh/v1.2.0-beta');
        config()->set('bkash.path_prefix', $legacy ? '/checkout' : '/tokenized/checkout');
        config()->set('bkash.paths', \App\Services\BkashService::pathsFor($generation));
        config()->set('bkash.id_in_path', $legacy);
        config()->set('bkash.script_url', 'https://scripts.pay.bka.sh/versions/1.2.0-beta/checkout/bKash-checkout.js');

        // Only a redirect flow needs a hosted page URL from the merchant portal.
        config()->set('bkash.checkout_url', $checkoutUrl);

        config()->set('bkash.username', '01608720460');
        config()->set('bkash.password', '2$1y2l5n=SE');
        config()->set('bkash.app_key', 'test-app-key');
        config()->set('bkash.app_secret', 'test-app-secret');
        config()->set('bkash.mfs_charge_percent', 1.5);
        config()->set('bkash.sandbox', $sandbox);
    }
}
