<?php

namespace App\Http\Middleware;

use App\Content\Site;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * The headers a browser needs to be told, because nothing was telling it any of them.
 *
 * The policy is built per request rather than written out once, for one reason: the Google
 * analytics ids are content. An editor can turn tracking on from Settings without a deploy, so a
 * fixed policy would either permanently allow Google on a site that never calls it, or silently
 * break tracking the moment somebody switched it on. It allows exactly what this request will use.
 *
 * Inline scripts carry a nonce instead of `'unsafe-inline'`. `Vite::useCspNonce()` puts the same
 * one on the tags Vite prints, and the two tracking snippets in the layout ask for it by name.
 *
 * `style-src` keeps `'unsafe-inline'` and that is not an oversight: React sets element styles
 * through the `style` prop, which is a style attribute, and the builder canvas is built the same
 * way. Removing it would mean rewriting how every component is styled for no attacker benefit
 * worth the change — script execution is what a policy is really for.
 */
class SecurityHeaders
{
    private const PERMISSIONS = 'accelerometer=(), autoplay=(), camera=(), display-capture=(), '
        .'encrypted-media=(), fullscreen=(self), geolocation=(), gyroscope=(), magnetometer=(), '
        .'microphone=(), midi=(), payment=(), usb=()';

    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        /* The media route sets its own, far tighter policy for the bytes it serves. Whatever a
           response has already decided about itself wins. */
        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', $this->policy($request, $nonce));
        }

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', self::PERMISSIONS);
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        /* Only over HTTPS. Sent over plain HTTP it is ignored by browsers and would pin a
           developer's machine to a scheme it is not serving. */
        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        /* PHP announces its version to anybody who asks. It is not a vulnerability; it is a free
           hint about which ones to try. `expose_php` adds it below Laravel, so the response bag
           alone does not carry it — `header_remove` is what actually drops it. */
        $response->headers->remove('X-Powered-By');

        if (! headers_sent()) {
            header_remove('X-Powered-By');
        }

        return $response;
    }

    private function policy(Request $request, string $nonce): string
    {
        $script = ["'self'", "'nonce-{$nonce}'"];
        $style = ["'self'", "'unsafe-inline'"];
        $font = ["'self'"];
        $connect = ["'self'"];

        /* Not `https:`. A blanket scheme here is an open exfiltration channel — `new Image().src` at
           any host on the internet needs no response to have already sent the query string — and it
           was by far the widest thing this policy allowed. Every image this site draws is served from
           its own media route, so naming the origin costs nothing. `data:` stays for inline SVG icons.
           Anything an editor pastes from elsewhere is refused at the point of saving instead, by
           `Html`, rather than being stored and then silently not drawn. */
        $img = ["'self'", 'data:'];

        /* The admin is the only thing that asks Google for a typeface. The public site bundles
           its own, and says so in the layout. */
        if ($request->is('cms', 'cms/*', 'login')) {
            $style[] = 'https://fonts.googleapis.com';
            $font[] = 'https://fonts.gstatic.com';

            /* An upload does not travel through PHP: the browser is handed a signed URL and PUTs
               the bytes at storage itself, which is never this origin. Without it the policy stops
               the request before it is sent, and the only symptom is the front end's "could not
               reach storage" — a message about a service that is running perfectly well. The admin
               is the only thing that uploads, so the public site is not given the origin. */
            if ($request->is('cms', 'cms/*') && $origin = $this->storageOrigin()) {
                $connect[] = $origin;
            }
        } else {
            foreach ($this->tracking() as $host) {
                $script[] = $host;
                $connect[] = $host;
                /* Analytics still measures some things with a pixel rather than a beacon, and these
                   hosts used to be covered by the blanket `https:` that has just gone. Named, so the
                   permission is as narrow as the thing it is for. */
                $img[] = $host;
            }
        }

        /* Development only, and only while the Vite server is actually running: hot reloading is
           a script and a websocket from another origin, which the policy would otherwise stop. */
        if (Vite::isRunningHot()) {
            $script[] = 'http://localhost:5173';
            $connect[] = 'http://localhost:5173';
            $connect[] = 'ws://localhost:5173';
            $style[] = 'http://localhost:5173';
        }

        return implode('; ', array_filter([
            "default-src 'self'",
            'script-src '.implode(' ', $script),
            'style-src '.implode(' ', $style),
            'img-src '.implode(' ', $img),
            'font-src '.implode(' ', $font),
            'connect-src '.implode(' ', $connect),
            "frame-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            $request->secure() ? 'upgrade-insecure-requests' : null,
        ]));
    }

    /**
     * Where signed uploads are sent, as a bare origin.
     *
     * `url` first: a bucket reached through a CDN is signed against that host, and the raw endpoint
     * would then be the wrong permission. The path is dropped deliberately — a CSP source matches by
     * path prefix, so leaving `/bucket` on would narrow the policy to a shape the signed URL may not
     * take, and it grants nothing extra to allow the host.
     */
    private function storageOrigin(): ?string
    {
        $configured = config('filesystems.disks.s3.url') ?: config('filesystems.disks.s3.endpoint');

        if (! is_string($configured) || $configured === '') {
            return null;
        }

        $parts = parse_url($configured);

        if (empty($parts['host'])) {
            return null;
        }

        $scheme = $parts['scheme'] ?? 'https';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return $scheme.'://'.$parts['host'].$port;
    }

    /**
     * The Google hosts this site will actually contact, and only when an editor has entered an id.
     * A policy that permits an analytics vendor on a site with no analytics is a policy nobody has
     * read.
     */
    private function tracking(): array
    {
        try {
            $tracking = Site::tracking();
        } catch (\Throwable $e) {
            /* Settings live in the database. A policy is not worth a 500 on a page that would
               otherwise render, so an unreachable database simply means no third party. */
            return [];
        }

        return $tracking === [] ? [] : [
            'https://www.googletagmanager.com',
            'https://www.google-analytics.com',
            'https://*.google-analytics.com',
            'https://*.analytics.google.com',
        ];
    }
}
