<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * The switch an admin flips in Admin -> Settings -> Maintenance. While it is
 * on, CloseSiteForMaintenance shows everyone except administrators the
 * maintenance page.
 *
 * This is separate from `php artisan down`, which also still works and shows
 * the same page: that one needs a shell, this one only needs the console.
 */
class MaintenanceMode
{
    public const MESSAGE_MAX = 300;

    private const ENABLED = 'maintenance.enabled';

    private const MESSAGE = 'maintenance.message';

    private const SINCE = 'maintenance.since';

    private const BY = 'maintenance.by';

    public function __construct(private readonly SiteSettings $settings) {}

    public function active(): bool
    {
        return $this->settings->get(self::ENABLED) === '1';
    }

    /** What visitors are told, if the admin wrote anything. */
    public function message(): ?string
    {
        return $this->settings->get(self::MESSAGE);
    }

    public function since(): ?Carbon
    {
        $since = $this->settings->get(self::SINCE);

        return $since !== null ? Carbon::parse($since) : null;
    }

    public function startedBy(): ?string
    {
        return $this->settings->get(self::BY);
    }

    public function enable(User $admin, ?string $message): void
    {
        // Keep the original start time when only the message changes.
        if (! $this->active()) {
            $this->settings->put(self::SINCE, now()->toIso8601String());
            $this->settings->put(self::BY, $admin->name);
        }

        $this->settings->put(self::MESSAGE, filled($message) ? trim($message) : null);
        $this->settings->put(self::ENABLED, '1');
    }

    public function disable(): void
    {
        $this->settings->put(self::ENABLED, null);
        $this->settings->put(self::SINCE, null);
        $this->settings->put(self::BY, null);
    }
}
