<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Foundation\Vite;
use PHPUnit\Framework\TestCase;

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
            [
                'The proxy in front is named',
                /* Judged against where this is heading, not where it is: locally the address is
                   plain http and no proxy exists, but `--production` is the question "would this be
                   right on the day", and on the day there is one. */
                filled(config('app.trusted_proxies'))
                    || (! $production && ! str_starts_with((string) config('app.url'), 'https://')),
                'TRUSTED_PROXIES must name the proxy terminating TLS. Without it every visitor shares '
                    .'one rate-limit bucket — six enquiries a minute for the whole internet — and HSTS '
                    .'is never sent, because the request does not look secure to PHP.',
            ],
            [
                'Rate limiting is not counting in the database',
                config('cache.default') !== 'database' || config('database.default') !== 'sqlite',
                'Optional. Every throttled request writes to the cache table, and SQLite takes a '
                    .'database-wide write lock to do it. CACHE_STORE=file on a single server.',
                'optional',
            ],
            [
                'Development packages are not installed',
                /* Optional, and it has to be: this command is itself run by the test suite, where
                   PHPUnit is present and correct. On a server its presence means the release step
                   ran a plain `composer install`, which is a signal about the rest of the step. */
                ! class_exists(TestCase::class),
                'Optional. `composer install --no-dev --optimize-autoloader` — the test suite, the '
                    .'formatter and the log viewer have no business on a live site, and their being '
                    .'there suggests the rest of the release step was skipped too.',
                'optional',
            ],
            [
                'No development build marker is present',
                ! file_exists(app(Vite::class)->hotFile()),
                'public/hot is how a developer\'s machine says "assets are coming from Vite". On a '
                    .'server every page then asks a dev server that is not there and renders blank, '
                    .'with the reason only in the browser console. `rm -f public/hot` in the release step.',
            ],
            [
                'Media storage is real object storage',
                ! self::looksLocal((string) config('filesystems.disks.s3.endpoint')),
                'AWS_ENDPOINT names a local S3 emulator — floci, from docker-compose.yml, which is a '
                    .'developer convenience and is never deployed. Uploads would live in its volume: no '
                    .'versioning, no backup, and gone with the container. Leave AWS_ENDPOINT unset for AWS.',
            ],
            [
                'The media bucket is configured',
                filled(config('filesystems.disks.s3.bucket')) && filled(config('filesystems.disks.s3.key')),
                'Without AWS_BUCKET and credentials every upload fails at the browser, and nothing in the '
                    .'log says so: the disk is deliberately configured not to throw, so a missing bucket '
                    .'looks exactly like a working one until somebody tries to add a picture.',
            ],
            [
                'The inbox socket is encrypted',
                config('broadcasting.default') !== 'reverb'
                    || config('broadcasting.connections.reverb.options.scheme') === 'https',
                'REVERB_SCHEME=https once the site is served over HTTPS. A ws:// socket on an https page '
                    .'is blocked by the browser as mixed content, so the CMS silently stops updating.',
            ],
            [
                'Something is draining the queue',
                config('queue.default') !== 'sync' || config('broadcasting.default') === 'null',
                'Optional. The arrival notice is a queued job, so with no worker the CMS updates only '
                    .'when somebody looks — which is how it behaved before the socket existed.',
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

    /**
     * An endpoint on this machine, or on the emulator's port anywhere.
     *
     * The port is part of it on purpose: floci reached over a real hostname is still floci, which is
     * the shape a staging box ends up in when `.env.example` is copied instead of
     * `.env.production.example`. An unset endpoint is the correct production value and passes.
     */
    private static function looksLocal(string $endpoint): bool
    {
        if ($endpoint === '') {
            return false;
        }

        $host = strtolower((string) (parse_url($endpoint, PHP_URL_HOST) ?: $endpoint));

        return in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0', 'host.docker.internal'], true)
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.localhost')
            || parse_url($endpoint, PHP_URL_PORT) === 4566;
    }
}
