<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\Permit;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    /* Registered here rather than through `withRouting(channels:)` so the authorisation endpoint can
       carry a limit: the framework registers it for us, so there is no line in `routes/web.php` to
       attach one to, and a socket that keeps dropping asks in bursts. `/up` deliberately gets none —
       a 429 on a health check is a supervisor restarting a perfectly healthy application. */
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['middleware' => ['web', 'throttle:broadcasting']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        /* Every response, not only the web group: the media route and the sitemap are worth the
           nosniff and the framing rules as much as a page is. */
        $middleware->append(SecurityHeaders::class);

        /* Which proxy to believe is set in `AppServiceProvider`, not here: this closure runs before
           configuration is loaded, and reading it from `env()` instead would return nothing at all on
           a server that has run `config:cache`. */
        $middleware->trustProxies(headers: Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO);

        $middleware->alias([
            'permit' => Permit::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('cms.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        /*
         * What being refused looks like. Three audiences, and the third one is a trap.
         *
         * The admin gets its errors back through Inertia, where `Builder.jsx` already raises a toast
         * and leaves the draft marked unsaved — the same path a validation failure takes.
         *
         * A reader gets a page that says we are busy, in words, with no number in it.
         *
         * The public enquiry post gets neither: it stays a plain refusal on purpose. Both forms treat
         * a request that neither succeeded nor failed as "we could not send that just now", which is
         * the honest sentence. A redirect here would arrive as a *successful* Inertia visit and thank
         * somebody for an enquiry that was never saved — the most expensive possible bug on a site
         * whose entire funnel is that form.
         */
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            $seconds = (int) ($e->getHeaders()['Retry-After'] ?? 60);
            $words = $seconds > 90 ? ceil($seconds / 60).' minutes' : 'a minute';

            if ($request->routeIs('enquiries.store') || $request->expectsJson()) {
                return null;
            }

            if ($request->hasHeader('X-Inertia') && $request->routeIs('cms.*')) {
                return back(303)
                    ->withErrors(['throttle' => "That was quicker than we can keep up with — try again in {$words}."])
                    ->withHeaders($e->getHeaders());
            }

            return response()->view('errors.429', ['words' => $words], 429, $e->getHeaders());
        });
    })->create();
