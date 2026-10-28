<?php

namespace App\Support;

use JsonSerializable;
use Stringable;

/**
 * A sentence to translate later, in the reader's language rather than the
 * writer's: a translation key with its values. Notifications and the trip
 * timeline are written by one traveller and read by others, each in the
 * language they chose, so they carry Phrases instead of finished text.
 *
 * Values may themselves be Phrases (a category label) or Amounts (money,
 * written with Bangla digits for Bangla readers); both render at read time.
 */
final class Phrase implements JsonSerializable, Stringable
{
    /** @param  array<string, mixed>  $replace */
    public function __construct(public readonly string $key, public readonly array $replace = []) {}

    /** @param  array<string, mixed>  $replace */
    public static function make(string $key, array $replace = []): self
    {
        return new self($key, $replace);
    }

    /** A date, written for whoever reads the sentence. */
    public static function date(\Carbon\CarbonInterface $date, string $format = 'D j M'): When
    {
        return When::of($date, $format);
    }

    /** Money, formatted for whoever reads the sentence. */
    public static function money(float|int|string|null $amount, string $currency = 'BDT'): Amount
    {
        return new Amount((float) ($amount ?? 0), $currency);
    }

    public function render(?string $locale = null): string
    {
        $replace = array_map(fn ($value) => match (true) {
            $value instanceof self, $value instanceof Amount, $value instanceof When => $value->render($locale),
            default => (string) $value,
        }, $this->replace);

        return __($this->key, $replace, $locale);
    }

    public function __toString(): string
    {
        return $this->render();
    }

    /** @return array{key: string, replace: array<string, mixed>} */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'replace' => array_map(fn ($value) => match (true) {
                $value instanceof self => ['phrase' => $value->toArray()],
                $value instanceof Amount => ['amount' => $value->toArray()],
                $value instanceof When => ['when' => $value->toArray()],
                default => $value,
            }, $this->replace),
        ];
    }

    /** @param  array{key?: string, replace?: array<string, mixed>}  $data */
    public static function fromArray(array $data): self
    {
        return new self((string) ($data['key'] ?? ''), array_map(fn ($value) => match (true) {
            is_array($value) && isset($value['phrase']) => self::fromArray($value['phrase']),
            is_array($value) && isset($value['amount']) => Amount::fromArray($value['amount']),
            is_array($value) && isset($value['when']) => When::fromArray($value['when']),
            default => $value,
        }, $data['replace'] ?? []));
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
