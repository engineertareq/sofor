<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Database\QueryException;

/**
 * Platform wide values from the `settings` table (branding, maintenance).
 * Bound as a singleton, so every page reads the table once per request.
 */
class SiteSettings
{
    /** @var array<string, string|null>|null */
    private ?array $values = null;

    public function get(string $key): ?string
    {
        if ($this->values === null) {
            try {
                $this->values = Setting::query()->pluck('value', 'key')->all();
            } catch (QueryException) {
                // Before `php artisan migrate` creates the table the site keeps
                // working with its built in defaults.
                $this->values = [];
            }
        }

        return $this->values[$key] ?? null;
    }

    /** Store a value; null removes it. */
    public function put(string $key, ?string $value): void
    {
        if ($value === null) {
            Setting::query()->whereKey($key)->delete();
        } else {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        $this->values = null;
    }
}
