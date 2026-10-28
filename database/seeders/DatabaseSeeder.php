<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Sofor intentionally ships without demo data: a fresh install starts empty, so
 * nothing fake ever appears on a live page.
 *
 * There are no seeded credentials either. The first account that registers on a
 * brand new install automatically becomes the administrator, which is how you
 * reach the admin console at /admin.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Nothing to seed. If you need fixtures for local development, add your
        // own seeder here and keep it out of production runs.
    }
}
