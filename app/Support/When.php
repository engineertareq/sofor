<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Stringable;

/** A date inside a Phrase: written (Tue 20 Oct, or in Bangla) when it is read. */
final class When implements Stringable
{
    public function __construct(public readonly string $iso, public readonly string $format = 'D j M') {}

    public static function of(CarbonInterface $date, string $format = 'D j M'): self
    {
        return new self($date->toIso8601String(), $format);
    }

    public function render(?string $locale = null): string
    {
        $date = Carbon::parse($this->iso)->locale($locale ?? app()->getLocale());

        return Bangla::active() || $locale === 'bn'
            ? Bangla::digits($date->translatedFormat($this->format))
            : $date->translatedFormat($this->format);
    }

    public function __toString(): string
    {
        return $this->render();
    }

    /** @return array{iso: string, format: string} */
    public function toArray(): array
    {
        return ['iso' => $this->iso, 'format' => $this->format];
    }

    /** @param  array{iso?: string, format?: string}  $data */
    public static function fromArray(array $data): self
    {
        return new self((string) ($data['iso'] ?? now()->toIso8601String()), (string) ($data['format'] ?? 'D j M'));
    }
}
