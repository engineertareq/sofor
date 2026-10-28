<?php

namespace App\Support;

use Stringable;

/** An amount inside a Phrase: formatted (৳1,500 or ৳১,৫০০) when it is read, not when it is written. */
final class Amount implements Stringable
{
    public function __construct(public readonly float $value, public readonly string $currency = 'BDT') {}

    public function render(?string $locale = null): string
    {
        if ($locale === null || $locale === app()->getLocale()) {
            return Money::format($this->value, $this->currency);
        }

        $previous = app()->getLocale();
        app()->setLocale($locale);

        try {
            return Money::format($this->value, $this->currency);
        } finally {
            app()->setLocale($previous);
        }
    }

    public function __toString(): string
    {
        return $this->render();
    }

    /** @return array{value: float, currency: string} */
    public function toArray(): array
    {
        return ['value' => $this->value, 'currency' => $this->currency];
    }

    /** @param  array{value?: float|int|string, currency?: string}  $data */
    public static function fromArray(array $data): self
    {
        return new self((float) ($data['value'] ?? 0), (string) ($data['currency'] ?? 'BDT'));
    }
}
