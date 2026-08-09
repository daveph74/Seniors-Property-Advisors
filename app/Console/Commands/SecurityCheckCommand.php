<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * The settings that have to be right on the day, checked rather than remembered.
 *
 * A security review can write "set SESSION_SECURE_COOKIE once you are on HTTPS" and be perfectly
 * correct and still be missed, because nothing reads the review at deploy time. This is the same
 * list as a command, so it can be run — or wired into a deploy step — and answer for itself.
 *
 * It reports rather than enforces. Refusing to boot on a misconfiguration would turn a warning
 * into an outage, and the environments this runs in are not all production.
 */
class SecurityCheckCommand extends Command
{
    protected $signature = 'security:check {--production : Judge against production expectations regardless of APP_ENV}';

    protected $description = 'Check the deployment settings a live site depends on';

    public function handle(): int
    {
        $production = $this->option('production') || app()->environment('production');

        $checks = [
            [
                'Debug mode is off',
                config('app.debug') === false,
                'APP_DEBUG=true prints stack traces, environment variables and queries to whoever triggers an error.',
            ],
            [
                'An application key is set',
                filled(config('app.key')),
                'Without APP_KEY nothing encrypted — sessions and cookies included — can be trusted.',
            ],
            [
                'Session cookies are HTTPS-only',
                config('session.secure') === true,
                'SESSION_SECURE_COOKIE=true stops the session cookie being sent over plain HTTP.',
            ],
            [
                'Session cookies are HTTP-only',
                config('session.http_only') === true,
                'Otherwise any script on the page can read the session cookie.',
            ],
            [
                'Session cookies are same-site',
                in_array(config('session.same_site'), ['lax', 'strict'], true),
                'A cookie sent on cross-site requests is a cross-site request forgery waiting to happen.',
            ],
            [
                'The site address is HTTPS',
                str_starts_with((string) config('app.url'), 'https://'),
                'APP_URL is used to build canonical URLs, sitemap entries and sharing tags.',
            ],
            [
                'Google Places key is set',
                filled(config('services.google.places_key')),
                'Optional. Without it the suburb field falls back to free text, which is by design.',
                'optional',
            ],
        ];

        $failed = 0;

        foreach ($checks as $check) {
            [$label, $passed, $why] = $check;
            $optional = ($check[3] ?? null) === 'optional';

            if ($passed) {
                $this->line("  <fg=green>PASS</>  {$label}");

                continue;
            }

            if ($optional || ! $production) {
                $this->line("  <fg=yellow>WARN</>  {$label} — {$why}");

                continue;
            }

            $this->line("  <fg=red>FAIL</>  {$label} — {$why}");
            $failed++;
        }

        $this->newLine();

        if (! $production) {
            $this->comment('Judged as a development environment. Run with --production to see what a live deploy must satisfy.');

            return self::SUCCESS;
        }

        if ($failed > 0) {
            $this->error("{$failed} setting(s) are not fit for a live site.");

            return self::FAILURE;
        }

        $this->info('Every deployment setting checks out.');

        return self::SUCCESS;
    }
}
