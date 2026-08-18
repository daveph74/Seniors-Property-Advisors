<?php

namespace Database\Seeders;

/**
 * A seeder that must never touch a live site.
 *
 * The docblocks said "local development accounts only" and "client copy replaces all of it", and
 * nothing enforced either. `php artisan db:seed --force` on a server is one plausible keystroke —
 * after a restore, or from somebody following a README — and `UserSeeder` matches on email with
 * `updateOrCreate`, so it would not merely add a test account: it would reset the real site
 * administrator's password to a well-known word and unstamp `password_changed_at` while doing it.
 *
 * Reported rather than thrown, so a seed run that reaches this stops being destructive without
 * becoming an exception somebody force-runs their way past.
 */
trait DevelopmentOnly
{
    private function refusedInProduction(): bool
    {
        if (! app()->environment('production')) {
            return false;
        }

        $this->command?->warn(static::class.' skipped: it is development data and this is production.');

        return true;
    }
}
