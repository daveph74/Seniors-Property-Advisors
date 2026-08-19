<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Ssr\BundleDetector;
use Tests\TestCase;

/**
 * Which pages are server-rendered, and what happens when the renderer is not there.
 *
 * What these cannot assert is the delivered `<h1>`. Real SSR HTML needs a node process listening on
 * 13714 with the current bundle, and `HttpGateway` answers `null` whenever it is absent — so a test
 * expecting a heading would fail on a fresh clone and pass vacuously the moment somebody weakened it.
 * The HTML is proved by hand and in the browser suite, where a build has just run. What is asserted here
 * is the decision: the public site goes to the renderer, the admin never does, and a missing renderer
 * degrades instead of erroring.
 *
 * One trap, paid for while writing these: Inertia memoises the render per request scope, so two page
 * visits inside one test reuse the first one's result. Each test below makes a single visit.
 */
class SsrScopeTest extends TestCase
{
    private function pretendTheRendererIsUp(): void
    {
        Http::fake([
            '127.0.0.1:13714/*' => Http::response(['head' => [], 'body' => '<h1>Rendered on the server</h1>']),
        ]);

        /* The bundle is a build artefact and is gitignored, so a checkout has none. */
        config(['inertia.ssr.enabled' => true, 'inertia.ssr.ensure_bundle_exists' => false]);
    }

    public function test_a_public_page_is_sent_to_the_renderer(): void
    {
        $this->pretendTheRendererIsUp();

        $this->get('/how-it-works')->assertOk();

        Http::assertSent(fn ($request) => str_contains($request->url(), '13714'));
    }

    /**
     * The admin is excluded by `HandleInertiaRequests::$withoutSsr`. Nothing crawls it — `robots.txt`
     * refuses it and it is behind sign-in — so rendering it twice would buy a slower response and pull
     * the editor and the socket client into a process with no browser to offer them.
     */
    public function test_the_admin_is_never_sent_to_the_renderer(): void
    {
        $this->pretendTheRendererIsUp();

        $this->get('/cms/pages')->assertOk();

        Http::assertNothingSent();
    }

    public function test_the_sign_in_screen_is_never_sent_to_the_renderer(): void
    {
        $this->pretendTheRendererIsUp();
        auth()->logout();

        $this->get('/login')->assertOk();

        Http::assertNothingSent();
    }

    /**
     * The contract the deployment check is predicated on: no renderer means the page still arrives, drawn
     * by the browser exactly as it was before any of this existed. A 500 here would take the site down to
     * protect its search ranking, which is the wrong way round.
     */
    public function test_a_missing_renderer_falls_back_rather_than_failing(): void
    {
        config(['inertia.ssr.enabled' => true, 'inertia.ssr.ensure_bundle_exists' => false]);
        Http::fake(['127.0.0.1:13714/*' => Http::response(status: 500)]);

        $this->get('/how-it-works')
            ->assertOk()
            ->assertSee('data-page', false);
    }

    /**
     * And the reason that graceful fallback needs a deployment check: it is indistinguishable from
     * working. A release that forgets the SSR build, or copies only tracked files over a gitignored
     * bundle, leaves a site that looks perfect and is no longer readable without JavaScript.
     */
    public function test_the_deployment_check_fails_when_the_bundle_is_missing(): void
    {
        config([
            'app.debug' => false,
            'session.secure' => true,
            'app.url' => 'https://example.com',
            'app.trusted_proxies' => '10.0.0.0/8',
            'filesystems.disks.s3.bucket' => 'spa-media',
            'filesystems.disks.s3.key' => 'AKIAREAL',
            'filesystems.disks.s3.endpoint' => null,
            'inertia.ssr.enabled' => true,
        ]);

        /* Stubbed rather than pointed at a missing path: `BundleDetector` falls through the config
           to a list of conventional locations, and this machine has a real bundle sitting in one of
           them. Faking the detector is what makes the assertion about the check rather than about
           whether a build happened to run here. */
        $this->app->instance(BundleDetector::class, new class extends BundleDetector
        {
            public function detect()
            {
                return null;
            }
        });

        $this->artisan('security:check --production')->assertFailed();

        /* And it passes again once SSR is deliberately off, because a site that has chosen not to
           server-render is configured, not broken. */
        config(['inertia.ssr.enabled' => false]);
        $this->artisan('security:check --production')->assertSuccessful();
    }

    /**
     * A spelling check rather than a behaviour test.
     *
     * `ssr.jsx` narrows its page glob to `./Pages/*.jsx`, which today is exactly the two components a
     * visitor can reach — everything else lives under `Pages/Cms/` or `Pages/Auth/`. That is a second
     * copy of "which pages are public", in another language, and this asserts the two still agree. If a
     * public page is ever added at a deeper path the glob will silently stop finding it, SSR will fall
     * back for that page alone, and nothing else would say so.
     */
    public function test_the_renderer_covers_every_page_the_public_can_reach(): void
    {
        $entry = file_get_contents(resource_path('js/ssr.jsx'));

        $this->assertStringContainsString("import.meta.glob('./Pages/*.jsx', { eager: true })", $entry);

        $reachable = ['AgentFinder', 'Article'];
        $covered = array_map(
            fn (string $path) => pathinfo($path, PATHINFO_FILENAME),
            glob(resource_path('js/Pages/*.jsx')) ?: [],
        );

        sort($covered);
        $this->assertSame($reachable, $covered, 'ssr.jsx globs one level, so a public page must live there');
    }

    public function test_a_client_administrator_still_reaches_the_admin_without_a_renderer(): void
    {
        $this->pretendTheRendererIsUp();
        $this->actingAs(User::factory()->create(['role' => User::CLIENT_ADMIN, 'is_active' => true]));

        $this->get('/cms/pages')->assertOk();

        Http::assertNothingSent();
    }
}
