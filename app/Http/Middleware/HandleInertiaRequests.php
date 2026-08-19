<?php

namespace App\Http\Middleware;

use App\Auth\Permissions;
use App\Cms\Notifications;
use App\Cms\Realtime;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * The admin is not server-rendered, and that is a decision rather than an omission.
     *
     * SSR exists here so a crawler receives a page with a heading and links in it. Nothing crawls
     * the admin — `robots.txt` refuses it and it is behind sign-in — so rendering it twice would
     * buy a slower response and pull the editor, the socket client and every admin screen into a
     * node process that has no browser to offer them. `resources/js/ssr.jsx` narrows its page glob
     * to the two public pages for the same reason; if these two ever disagree, the request simply
     * falls back to rendering in the browser, which is what it did before any of this.
     *
     * `app.blade.php` spells the same three paths out twice more, for the fonts and the analytics.
     * Left as three separate conditions on purpose: they answer different questions and a shared
     * helper would tie a stylesheet decision to a rendering one.
     *
     * @var array<int, string>
     */
    protected $withoutSsr = ['cms', 'cms/*', 'login'];

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user === null ? null : [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'roleLabel' => $user->roleLabel(),
                    'initials' => $user->initials(),
                ],
                'can' => Permissions::abilities($user),
                'modules' => Permissions::modules($user),
                'mustChangePassword' => $user !== null && $user->mustChangePassword(),
            ],
            /* So a form knows its enquiry arrived after the redirect back. */
            'enquiry' => fn () => $request->session()->get('enquiry'),
            /* The header's bell, and only where there is a header to put it in — the public site
               shares this middleware, and two counts per page view is a bill nobody asked for. */
            'notifications' => fn () => $request->routeIs('cms.*') ? Notifications::for() : null,
            /* What the admin should connect to, if anything — the same answer the content policy is
               built from, so the two cannot disagree about whether a socket exists. Only where the
               admin is: the public site has nothing to listen for. */
            'realtime' => fn () => $request->routeIs('cms.*') ? Realtime::config() : null,
        ];
    }
}
