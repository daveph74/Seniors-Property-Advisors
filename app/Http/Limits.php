<?php

namespace App\Http;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * What this application refuses, and why each number is the number.
 *
 * These are not an access control — `permit:` is that, and it runs first. They bound a runaway
 * script, a stolen session and a stranger with a word list. So they are set where no honest use
 * reaches them: a limit an editor meets during a normal afternoon is a bug report about a save
 * button that stopped working, and nobody will connect it to a security setting.
 *
 * Signed-in limits key on the account. Keying them on the address would mean one office sharing a
 * single allowance, so the second person to upload a photo is refused because of the first.
 *
 * Public limits key on the address, which is only meaningful once `TRUSTED_PROXIES` names the proxy —
 * see `bootstrap/app.php`. Without it every visitor is the proxy, and six enquiries a minute becomes
 * six for the whole internet.
 *
 * The numbers live in `config/limits.php` so they can be read together, and so a test can lower one.
 */
class Limits
{
    public static function define(): void
    {
        /* The media route is reached both signed in — the library grid — and not, from the public
           site, so it needs the account when there is one and the address when there is not. */
        $who = fn (Request $request) => $request->user()?->id ?: $request->ip();

        /* ------------------------------------------------------------------ signed in */

        /* Everything under /cms. Five a second sustained: above anything a person produces with a
           mouse, below anything a stolen session is worth using. */
        RateLimiter::for('cms', fn (Request $r) => Limit::perMinute(config('limits.cms.minute'))->by($who($r)));

        /* Saves, publishes, the diff, a long article body, the navigation and global-content
           replacements. Two a second, which is an order of magnitude above the fastest editor
           pressing Save. If draft saving ever becomes a real autosave on a timer, this is the number
           to revisit before the interval is chosen. */
        RateLimiter::for('cms-write', fn (Request $r) => Limit::perMinute(config('limits.cms_write.minute'))->by($who($r)));

        /* Uploads are the one place a person legitimately makes a hundred requests: each picture is a
           sign and then a store, so dropping forty images is eighty calls, and the bytes go straight
           to storage so they arrive fast. This is the limit that would ruin a real afternoon's work
           if it were set to the sixty that looks reasonable on paper. */
        RateLimiter::for('cms-upload', fn (Request $r) => Limit::perMinute(config('limits.cms_upload.minute'))->by($who($r)));

        /* The palette fires on a 200ms debounce, so a held-down key reaches five a second. The old
           120 sat close enough to that to refuse somebody typing a long query. */
        RateLimiter::for('cms-search', fn (Request $r) => Limit::perMinute(config('limits.cms_search.minute'))->by($who($r)));

        /* A credential change. Somebody does this once; ten an hour covers the retries when the
           breach corpus rejects a choice, and throttles that outbound call at the same time. */
        RateLimiter::for('password', fn (Request $r) => Limit::perHour(config('limits.password.hour'))->by($who($r)));

        /* --------------------------------------------------------------------- public */

        /* The sign-in form. The real defence is the per-email counter in `LoginRequest`; this bounds
           one address trying many addresses. The hourly ceiling is what a per-minute limit cannot
           see — a spray that stays politely under ten a minute all afternoon. */
        RateLimiter::for('sign-in', fn (Request $r) => [
            Limit::perMinute(config('limits.sign_in.minute'))->by($r->ip()),
            Limit::perHour(config('limits.sign_in.hour'))->by($r->ip()),
        ]);

        /* An enquiry. A real person sends one; six a minute already covers a double click and a
           correction. Twenty an hour still leaves room for a retirement village where several
           residents use the one terminal. */
        RateLimiter::for('enquiries', fn (Request $r) => [
            Limit::perMinute(config('limits.enquiries.minute'))->by($r->ip()),
            Limit::perHour(config('limits.enquiries.hour'))->by($r->ip()),
        ]);

        /* Suburb autocomplete. Every call is a paid Google request, so this is a bill as much as a
           defence. The field debounces at 250ms and a suburb takes about four calls: sixty a minute
           is fifteen suburbs a minute, which nobody types. */
        RateLimiter::for('suburbs', fn (Request $r) => [
            Limit::perMinute(config('limits.suburbs.minute'))->by($r->ip()),
            Limit::perHour(config('limits.suburbs.hour'))->by($r->ip()),
        ]);

        /* Reading the site. A person reads maybe ten pages a minute; a hundred and twenty leaves room
           for link prefetching and a shared address. */
        RateLimiter::for('public', fn (Request $r) => Limit::perMinute(config('limits.public.minute'))->by($r->ip()));

        /* Two uncached queries, and no crawler that behaves fetches it ten times a minute. */
        RateLimiter::for('sitemap', fn (Request $r) => Limit::perMinute(config('limits.sitemap.minute'))->by($r->ip()));

        /* Bytes through PHP, and deliberately the most generous number here. An image-heavy page is
           sixty requests at once, so anything near the public limit would leave a reader looking at
           broken pictures with nothing to explain them — the worst failure on a site built for people
           who will assume they did something wrong. It stops an enumerator walking the library, not a
           browser. The year-long immutable cache already removes the repeat visits; this covers what
           the cache cannot. */
        RateLimiter::for('media', fn (Request $r) => Limit::perMinute(config('limits.media.minute'))->by($who($r)));
    }
}
