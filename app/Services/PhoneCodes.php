<?php

namespace App\Services;

use App\Exceptions\SmsException;
use App\Models\User;
use App\Models\VerificationCode;
use App\Support\Phone;
use Illuminate\Validation\ValidationException;

/**
 * Six digit codes texted to a phone, to verify the number or to reset a
 * password. The rules every code follows are in OneTimeCodes.
 */
class PhoneCodes extends OneTimeCodes
{
    public function __construct(private readonly SmsService $sms) {}

    protected function destination(string $address): string
    {
        return (string) Phone::normalize($address);
    }

    protected function deliver(string $purpose, string $destination, string $code, ?User $user, string $field): void
    {
        try {
            $this->sms->send($destination, $this->message($purpose, $code));
        } catch (SmsException $e) {
            report($e);

            throw ValidationException::withMessages([$field => __('We could not text :phone just now. Please try again in a minute.', ['phone' => Phone::pretty($destination)])]);
        }
    }

    protected function tooManyMessage(): string
    {
        return __('Too many codes went to this number. Please try again in an hour.');
    }

    /** Plain ASCII and under 160 characters, so it always goes as one text. */
    private function message(string $purpose, string $code): string
    {
        return match ($purpose) {
            VerificationCode::PURPOSE_PASSWORD_RESET => "Sofor: {$code} is your password reset code. It expires in ".self::TTL_MINUTES.' minutes. If you did not ask for it, ignore this text.',
            default => "Sofor: {$code} is your verification code. It expires in ".self::TTL_MINUTES.' minutes. Never share it with anyone.',
        };
    }
}
