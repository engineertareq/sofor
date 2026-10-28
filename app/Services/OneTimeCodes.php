<?php

namespace App\Services;

use App\Models\User;
use App\Models\VerificationCode;
use App\Support\CodeCheck;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Six digit codes that prove somebody can read a phone (PhoneCodes) or an
 * inbox (EmailCodes).
 *
 * Only a keyed hash of each code is stored. A code works for TTL_MINUTES and
 * allows five tries; asking for a new one retires the old one. Sending costs
 * money or reputation and can be abused, so an address gets one code a minute
 * and five an hour at most (on top of the per IP route throttles).
 */
abstract class OneTimeCodes
{
    public const LENGTH = 6;

    public const TTL_MINUTES = 10;

    public const RESEND_SECONDS = 60;

    public const MAX_PER_HOUR = 5;

    public const MAX_ATTEMPTS = 5;

    /** The address as it is stored and compared. */
    abstract protected function destination(string $address): string;

    /**
     * Get the code to whoever holds the address.
     *
     * @throws ValidationException on $field when it could not be sent
     */
    abstract protected function deliver(string $purpose, string $destination, string $code, ?User $user, string $field): void;

    /** What to say once an address has had its codes for the hour. */
    abstract protected function tooManyMessage(): string;

    /** Seconds before another code may be sent for this purpose and address; 0 when one may. */
    public function cooldown(string $purpose, string $address): int
    {
        $last = $this->latest($purpose, $this->destination($address));

        if ($last === null) {
            return 0;
        }

        return max(0, static::RESEND_SECONDS - (int) $last->created_at->diffInSeconds(now()));
    }

    /** Whether a code went out recently enough to still be typed in. */
    public function outstanding(string $purpose, string $address): bool
    {
        return VerificationCode::query()
            ->where('purpose', $purpose)
            ->where('destination', $this->destination($address))
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->exists();
    }

    /**
     * Send a fresh code. Problems are reported as a validation error on
     * $field so the form shows them.
     *
     * @throws ValidationException
     */
    public function send(string $purpose, string $address, ?User $user = null, string $field = 'code'): void
    {
        $destination = $this->destination($address);

        if (($wait = $this->cooldown($purpose, $destination)) > 0) {
            throw ValidationException::withMessages([$field => "Please wait {$wait} seconds before asking for another code."]);
        }

        $sentThisHour = VerificationCode::query()
            ->where('purpose', $purpose)
            ->where('destination', $destination)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($sentThisHour >= static::MAX_PER_HOUR) {
            throw ValidationException::withMessages([$field => $this->tooManyMessage()]);
        }

        $code = str_pad((string) random_int(0, 10 ** static::LENGTH - 1), static::LENGTH, '0', STR_PAD_LEFT);

        $record = DB::transaction(function () use ($purpose, $destination, $user, $code) {
            // Only the newest code works.
            VerificationCode::query()
                ->where('purpose', $purpose)
                ->where('destination', $destination)
                ->whereNull('used_at')
                ->update(['used_at' => now()]);

            return VerificationCode::create([
                'user_id' => $user?->id,
                'purpose' => $purpose,
                'destination' => $destination,
                'code_hash' => $this->hash($purpose, $destination, $code),
                'expires_at' => now()->addMinutes(static::TTL_MINUTES),
            ]);
        });

        try {
            $this->deliver($purpose, $destination, $code, $user, $field);
        } catch (ValidationException $e) {
            // Not sent, so it must not count against the limits either.
            $record->delete();

            throw $e;
        }
    }

    /** Check a code. A valid code is used up; a wrong one costs a try. */
    public function verify(string $purpose, string $address, string $code): CodeCheck
    {
        $destination = $this->destination($address);
        $record = $this->latest($purpose, $destination);

        if ($record === null || $record->used_at !== null || $record->expires_at->isPast()) {
            return CodeCheck::Expired;
        }

        if ($record->attempts >= static::MAX_ATTEMPTS) {
            return CodeCheck::TooManyAttempts;
        }

        // Count the try before comparing, so parallel guesses cannot slip past the limit.
        $record->increment('attempts');

        $code = preg_replace('/\D+/', '', $code) ?? '';

        if (! hash_equals($record->code_hash, $this->hash($purpose, $destination, $code))) {
            return $record->attempts >= static::MAX_ATTEMPTS ? CodeCheck::TooManyAttempts : CodeCheck::Wrong;
        }

        $record->forceFill(['used_at' => now()])->save();

        return CodeCheck::Valid;
    }

    private function latest(string $purpose, string $destination): ?VerificationCode
    {
        return VerificationCode::query()
            ->where('purpose', $purpose)
            ->where('destination', $destination)
            ->latest('id')
            ->first();
    }

    private function hash(string $purpose, string $destination, string $code): string
    {
        return hash_hmac('sha256', $purpose.'|'.$destination.'|'.$code, (string) config('app.key'));
    }
}
