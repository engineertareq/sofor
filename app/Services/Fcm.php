<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Push alerts to the Sofor app through Firebase Cloud Messaging (the HTTP v1
 * API). It signs in to Google with the service account file named in
 * FIREBASE_CREDENTIALS; with no file set, Sofor simply sends no pushes.
 *
 * Plain HTTP on purpose: it runs on shared hosting, with no extra packages.
 */
class Fcm
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    public function configured(): bool
    {
        $path = (string) config('services.fcm.credentials');

        return $path !== '' && is_readable($path);
    }

    /**
     * One alert to each of a traveller's phones.
     *
     * @param  list<string>  $tokens
     * @param  array<string, scalar|null>  $data  what the app needs to open the right screen
     * @return list<string> tokens Firebase no longer knows: the app was removed, so forget them
     */
    public function send(array $tokens, string $title, string $body, array $data = [], string $channel = 'trips'): array
    {
        if ($tokens === [] || ! $this->configured()) {
            return [];
        }

        $account = $this->account();
        $access = $this->accessToken($account);
        $gone = [];

        foreach ($tokens as $token) {
            $response = Http::withToken($access)
                ->timeout(10)
                ->post("https://fcm.googleapis.com/v1/projects/{$account['project_id']}/messages:send", [
                    'message' => [
                        'token' => $token,
                        'notification' => ['title' => $title, 'body' => $body],
                        // FCM data values are strings.
                        'data' => array_map(fn ($value) => (string) $value, array_filter($data, fn ($value) => $value !== null)),
                        'android' => [
                            'priority' => 'high',
                            'notification' => ['channel_id' => $channel],
                        ],
                    ],
                ]);

            $status = (string) $response->json('error.status');

            if ($response->status() === 404 || $status === 'UNREGISTERED' || $status === 'INVALID_ARGUMENT') {
                $gone[] = $token;
            } elseif ($response->failed()) {
                report(new RuntimeException('FCM refused a push: '.$response->status().' '.$status));
            }
        }

        return $gone;
    }

    /** @return array{client_email: string, private_key: string, project_id: string} */
    private function account(): array
    {
        $account = json_decode((string) file_get_contents((string) config('services.fcm.credentials')), true);

        if (! is_array($account) || ! isset($account['client_email'], $account['private_key'], $account['project_id'])) {
            throw new RuntimeException('FIREBASE_CREDENTIALS is not a service account file.');
        }

        return $account;
    }

    /** Google's access token for the service account, kept until shortly before it runs out. */
    private function accessToken(array $account): string
    {
        return Cache::remember('fcm.access-token.'.md5($account['client_email']), now()->addMinutes(50), function () use ($account) {
            $now = time();
            $encode = fn (array $part) => rtrim(strtr(base64_encode((string) json_encode($part)), '+/', '-_'), '=');

            $unsigned = $encode(['alg' => 'RS256', 'typ' => 'JWT']).'.'.$encode([
                'iss' => $account['client_email'],
                'scope' => self::SCOPE,
                'aud' => self::TOKEN_URL,
                'iat' => $now,
                'exp' => $now + 3600,
            ]);

            if (! openssl_sign($unsigned, $signature, $account['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('Could not sign the Firebase sign-in request.');
            }

            $response = Http::asForm()->timeout(10)->post(self::TOKEN_URL, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $unsigned.'.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '='),
            ])->throw();

            return (string) $response->json('access_token');
        });
    }
}
