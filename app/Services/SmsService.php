<?php

namespace App\Services;

use App\Exceptions\SmsException;
use App\Support\Phone;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends text messages: phone verification and password reset codes.
 *
 * Driver "smsnetbd" goes through sms.net.bd (POST /sendsms with api_key, msg
 * and to; it answers {"error": 0, ...} on success). Driver "log" only writes
 * the message to the log, for local development and tests.
 */
class SmsService
{
    /** What sms.net.bd's error codes mean, for the log. */
    private const ERRORS = [
        400 => 'a parameter is missing or invalid',
        403 => 'no permission for this request',
        404 => 'resource not found',
        405 => 'authorization required (check SMS_NET_BD_API_KEY)',
        409 => 'unknown error on the gateway',
        410 => 'the account has expired',
        411 => 'the reseller account has expired or is suspended',
        412 => 'invalid schedule',
        413 => 'invalid sender ID (check SMS_NET_BD_SENDER_ID)',
        414 => 'the message is empty',
        415 => 'the message is too long',
        416 => 'no valid number found',
        417 => 'insufficient balance',
        420 => 'the content was blocked',
    ];

    /** @throws SmsException when the message could not be handed to the gateway */
    public function send(string $phone, string $message): void
    {
        $normalized = Phone::normalize($phone);

        if ($normalized === null || ! Phone::isValid($normalized)) {
            throw new SmsException('Not a Bangladeshi mobile number: '.$phone);
        }

        // sms.net.bd wants 8801XXXXXXXXX, without the plus.
        $to = ltrim($normalized, '+');

        if (config('services.sms.driver') !== 'smsnetbd') {
            Log::info('SMS (not sent, SMS_DRIVER=log)', ['to' => $to, 'message' => $message]);

            return;
        }

        $config = config('services.sms.smsnetbd');

        if (blank($config['key'] ?? null)) {
            throw new SmsException('SMS_NET_BD_API_KEY is not set.');
        }

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->timeout(15)
                ->post(rtrim($config['url'], '/').'/sendsms', array_filter([
                    'api_key' => $config['key'],
                    'msg' => $message,
                    'to' => $to,
                    'sender_id' => $config['sender_id'] ?? null,
                ], fn ($value) => filled($value)));
        } catch (ConnectionException $e) {
            throw new SmsException('Could not reach sms.net.bd: '.$e->getMessage());
        }

        $code = $response->json('error');

        if (! $response->successful() || ! is_numeric($code) || (int) $code !== 0) {
            $code = is_numeric($code) ? (int) $code : null;

            throw new SmsException(
                'sms.net.bd refused the message: '.(self::ERRORS[$code] ?? $response->json('msg') ?? 'HTTP '.$response->status()),
                $code,
            );
        }
    }
}
