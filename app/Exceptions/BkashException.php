<?php

namespace App\Exceptions;

use RuntimeException;

class BkashException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(string $message, public array $payload = [], public ?string $step = null)
    {
        parent::__construct($message);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromResponse(string $step, array $payload): self
    {
        $message = $payload['statusMessage']
            ?? $payload['errorMessage']
            ?? $payload['message']
            ?? 'bKash rejected the request.';

        return new self($step.': '.$message, $payload, $step);
    }
}
