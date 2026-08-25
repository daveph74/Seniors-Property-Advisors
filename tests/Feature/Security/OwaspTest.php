<?php

namespace Tests\Feature\Security;

use App\Models\Activity;
use App\Models\BlogPost;
use App\Models\Enquiry;
use App\Models\Faq;
use App\Models\Media;
use App\Models\Page;
use App\Models\User;
use Database\Seeders\SampleContentSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The OWASP Top 10 (2021), walked category by category against this application.
 *
 * These are not a substitute for the per-feature tests — `PermissionsTest` covers the route matrix
 * far better than A01 does here. They exist so that a category nobody has thought about since the
 * review cannot quietly stop being true, and so the next person can see which of the ten were
 * argued about rather than assumed.
 *
 * Where a category does not apply, the test says so and asserts the reason instead of being
 * silently absent.
 */
class OwaspTest extends TestCase
{
    // ---------------------------------------------------------------- A01: Broken access control

    public function test_a01_every_cms_route_refuses_a_visitor_who_is_not_signed_in(): void
    {
        auth()->logout();

        foreach (['/cms', '/cms/pages', '/cms/blog', '/cms/media', '/cms/enquiries', '/cms/users', '/cms/settings'] as $path) {
            $this->get($path)->assertRedirect('/login');
        }
    }

    public function test_a01_a_disabled_account_loses_access_on_its_next_request(): void
    {
        $user = $this->clientAdmin();
        $this->actingAs($user);
        $this->get('/cms')->assertOk();

        $user->update(['is_active' => false]);

        /* Not on next sign-in — now. The middleware checks the flag, not a claim in the session. */
        $this->get('/cms')->assertForbidden();
    }

    public function test_a01_a_client_administrator_cannot_reach_a_super_administrator_route(): void
    {
        $this->actingAs($this->clientAdmin());

        $this->get('/cms/users')->assertForbidden();
        $this->get('/cms/settings')->assertForbidden();
        $this->get('/cms/deleted')->assertForbidden();
    }

    /** Every delete in the application is a super-administrator act. §2 gives client users disable and archive only. */
    public function test_a01_no_delete_route_is_open_to_a_client_administrator(): void
    {
        $faq = Faq::create(['question' => 'q', 'answer' => 'a', 'active' => true]);
        $post = BlogPost::create(['slug' => 'p', 'title' => 'P', 'body' => '<p>b</p>', 'status' => 'draft']);
        $media = Media::create(['key' => '2026/08/a.jpg', 'name' => 'a.jpg', 'mime' => 'image/jpeg', 'size' => 1, 'disk' => 's3']);
        $enquiry = Enquiry::create(['name' => 'A', 'email' => 'a@example.com', 'consented' => true]);

        $this->actingAs($this->clientAdmin());

        $this->delete("/cms/faqs/{$faq->id}")->assertForbidden();
        $this->delete("/cms/blog/{$post->id}")->assertForbidden();
        $this->delete("/cms/media/{$media->id}")->assertForbidden();
        $this->delete("/cms/enquiries/{$enquiry->id}")->assertForbidden();

        $this->assertDatabaseHas('faqs', ['id' => $faq->id]);
        $this->assertDatabaseHas('enquiries', ['id' => $enquiry->id]);
    }

    /** Nothing is authorised by an id in the request. Guessing one gets a 404, not somebody else's row. */
    public function test_a01_an_unknown_id_is_not_a_way_in(): void
    {
        $this->get('/cms/pages/99999/edit')->assertNotFound();
        $this->get('/cms/blog/99999/edit')->assertNotFound();
        $this->patch('/cms/enquiries/99999/status', ['status' => Enquiry::DEALT_WITH])->assertNotFound();
    }

    // ------------------------------------------------------------- A02: Cryptographic failures

    public function test_a02_passwords_are_hashed_and_never_returned(): void
    {
        $user = $this->clientAdmin(['email' => 'hash@example.com']);

        $this->assertNotSame('password', $user->password);
        $this->assertTrue(Hash::check('password', $user->password));
        $this->assertStringStartsWith('$2y$', $user->password);

        $this->assertArrayNotHasKey('password', $user->toArray());
        $this->assertArrayNotHasKey('remember_token', $user->toArray());
    }

    public function test_a02_the_signed_in_user_prop_carries_no_credential(): void
    {
        $props = $this->get('/cms')->viewData('page')['props'];

        $this->assertArrayNotHasKey('password', $props['auth']['user']);
        $this->assertStringNotContainsString('$2y$', json_encode($props));
    }

    public function test_a02_session_cookies_are_http_only_and_same_site(): void
    {
        $this->assertTrue(config('session.http_only'));
        $this->assertContains(config('session.same_site'), ['lax', 'strict']);
    }

    // ------------------------------------------------------------------------- A03: Injection

    /** LIKE wildcards are escaped, so a search for one matches nothing rather than everything. */
    public function test_a03_a_wildcard_in_a_search_is_a_literal(): void
    {
        BlogPost::create(['slug' => 'a', 'title' => 'Alpha', 'body' => '<p>b</p>', 'status' => 'published']);
        BlogPost::create(['slug' => 'b', 'title' => 'Beta', 'body' => '<p>b</p>', 'status' => 'published']);

        $groups = $this->getJson('/cms/search?q='.urlencode('%'))->assertOk()->json('groups');

        $this->assertSame([], $groups);
    }

    public function test_a03_a_quote_in_a_search_does_not_break_the_query(): void
    {
        foreach (["' OR 1=1 --", '"; drop table users; --', "\\'"] as $payload) {
            $this->getJson('/cms/search?q='.urlencode($payload))->assertOk();
            $this->get('/cms/enquiries?q='.urlencode($payload))->assertOk();
        }

        $this->assertDatabaseCount('users', 1);
    }

    /** Article bodies are HTML, so the purifier is the whole defence. */
    public function test_a03_script_never_survives_into_a_stored_article(): void
    {
        $payloads = [
            '<script>alert(1)</script><p>Kept</p>',
            '<p onclick="alert(1)">Kept</p>',
            '<a href="javascript:alert(1)">Kept</a>',
            '<img src=x onerror="alert(1)">',
            '<iframe src="https://evil.example"></iframe><p>Kept</p>',
        ];

        foreach ($payloads as $i => $body) {
            $this->post('/cms/blog', [
                'title' => "Article {$i}", 'slug' => "article-{$i}", 'body' => $body, 'status' => 'draft',
            ]);
        }

        foreach (BlogPost::all() as $post) {
            $this->assertStringNotContainsString('<script', $post->body);
            $this->assertStringNotContainsString('onerror', $post->body);
            $this->assertStringNotContainsString('onclick', $post->body);
            $this->assertStringNotContainsString('javascript:', $post->body);
            $this->assertStringNotContainsString('<iframe', $post->body);
        }
    }

    /** Section trees are not HTML at all, so every string in them is stripped of tags. */
    public function test_a03_markup_cannot_be_stored_in_a_section_tree(): void
    {
        $page = Page::where('status', 'published')->firstOrFail();

        $this->post("/cms/pages/{$page->cms_id}/draft", [
            'sections' => [[
                'id' => 'cta-1', 'type' => 'cta', 'label' => 'Call to action', 'active' => true,
                'data' => ['heading' => '<script>alert(1)</script>Hello'], 'children' => [],
            ]],
        ])->assertSessionHasNoErrors();

        $draft = json_encode($page->refresh()->draft);

        $this->assertStringNotContainsString('<script', $draft);
        $this->assertStringContainsString('Hello', $draft);
    }

    // --------------------------------------------------------------------- A04: Insecure design

    /*
     * The limits below set their own numbers through `config('limits.…')` rather than looping to the
     * real ceiling. Three requests to prove a refusal instead of a hundred and twenty-one is the
     * difference between a security suite that runs and one nobody waits for — and it is why those
     * numbers live in a config file at all.
     */

    public function test_a04_two_editors_behind_one_address_do_not_share_a_limit(): void
    {
        /* The keying guarantee, and the one test that would catch a regression to keying on the
           address: an office shares one, so the second person to save would be refused because of the
           first, and it would look like the CMS breaking at random. */
        config(['limits.cms.minute' => 3]);

        $first = $this->clientAdmin(['email' => 'first@example.com']);
        $second = $this->clientAdmin(['email' => 'second@example.com']);

        $this->actingAs($first);

        for ($i = 0; $i < 3; $i++) {
            $this->get('/cms')->assertOk();
        }

        $this->get('/cms')->assertStatus(429);

        $this->actingAs($second);
        $this->get('/cms')->assertOk();
    }

    public function test_a04_a_page_worth_of_images_is_not_turned_away(): void
    {
        /* Named because the media number is the one most likely to be tightened later by somebody
           being careful, and the failure is silent: broken pictures, no message, on a site whose
           readers will assume they did something wrong. A single page legitimately asks for dozens. */
        auth()->logout();
        config(['limits.public.minute' => 2, 'limits.media.minute' => 60]);

        Storage::fake('s3');
        Storage::disk('s3')->put('2026/08/photo.png', 'bytes');
        Media::create([
            'key' => '2026/08/photo.png', 'name' => 'photo.png',
            'mime' => 'image/png', 'size' => 5, 'disk' => 's3',
        ]);

        $this->get('/')->assertOk();
        $this->get('/')->assertOk();
        $this->get('/')->assertStatus(429, 'the page limit is reached');

        for ($i = 0; $i < 40; $i++) {
            $this->get('/media/2026/08/photo.png')->assertOk();
        }
    }

    public function test_a04_a_credential_change_is_rate_limited(): void
    {
        $user = $this->clientAdmin(['email' => 'target@example.com']);
        $this->actingAs($user);

        config(['limits.password.hour' => 2]);

        $change = fn (string $password) => $this->patch('/cms/account/password', [
            'current_password' => 'password',
            'password' => $password,
            'password_confirmation' => $password,
        ]);

        $change('short');
        $change('short');

        $change('Str0ng-Enough!2026')->assertStatus(429);

        $this->assertTrue(Hash::check('password', $user->refresh()->password));
    }

    public function test_a04_the_health_check_is_never_rate_limited(): void
    {
        /* Cheap, and it pins the exemption that stops a limiter causing the outage it was added to
           prevent: a 429 here is a supervisor restarting a perfectly healthy application. */
        auth()->logout();
        config(['limits.public.minute' => 1]);

        for ($i = 0; $i < 30; $i++) {
            $this->get('/up')->assertOk();
        }
    }

    public function test_a04_a_refusal_says_when_to_come_back_and_never_looks_like_success(): void
    {
        auth()->logout();
        config(['limits.public.minute' => 1, 'limits.enquiries.minute' => 1]);

        $this->get('/');
        $page = $this->get('/');

        $page->assertStatus(429);
        $page->assertHeader('Retry-After');
        /* Written for the reader: no stack trace, no jargon, and not the number 429. */
        $page->assertSee('busy', false);
        $page->assertDontSee('429');

        $enquiry = fn () => $this->post('/enquiries', [
            'name' => 'Janet', 'email' => 'janet@example.com', 'consent' => true,
        ], ['X-Inertia' => 'true']);

        $enquiry();
        $refused = $enquiry();

        /*
         * A refusal on this route must never be a redirect. Both public forms read a request that
         * neither succeeded nor failed as "we could not send that just now"; a redirect would arrive
         * as a successful Inertia visit and thank somebody for an enquiry that was never saved.
         */
        $refused->assertStatus(429);
        $this->assertSame(1, Enquiry::count());
    }

    public function test_a04_a_locked_out_sign_in_is_recorded_without_the_address(): void
    {
        /*
         * Ties this category to the A09 rule further down: `activity_log` has no delete path, and the
         * email in a failed attempt is unverified and belongs to somebody who is not a user here. The
         * log file gets the account id when the address matches a real one, and a hash when it does
         * not — enough to tell five hundred attempts on one account from five hundred accounts.
         */
        auth()->logout();
        Log::spy();

        $before = Activity::count();

        for ($i = 0; $i < 6; $i++) {
            $this->post('/login', ['email' => 'stranger@example.com', 'password' => 'wrong']);
        }

        Log::shouldHaveReceived('warning')->atLeast()->once();
        Log::shouldHaveReceived('info')->atLeast()->once();

        $this->assertSame($before, Activity::count(), 'a stranger never reaches the audit log');
        $this->assertDatabaseMissing('activity_log', ['subject_label' => 'stranger@example.com']);
    }

    public function test_a04_the_public_form_is_rate_limited(): void
    {
        auth()->logout();

        $send = fn () => $this->post('/enquiries', [
            'name' => 'Flood', 'email' => 'flood@example.com', 'consent' => true,
        ]);

        for ($i = 0; $i < 6; $i++) {
            $send();
        }

        $send()->assertStatus(429);

        /* Both public forms post here, which is the reason there is no second route: the seventh
           attempt is turned away whichever of them makes it. A form with an endpoint of its own would
           be a public write path this test does not know exists. */
        $this->post('/enquiries', [
            'source' => Enquiry::FIND_MY_AGENT,
            'name' => 'Flood', 'email' => 'flood@example.com', 'phone' => '0400 000 000', 'consent' => true,
            'details' => [
                'property_type' => 'house', 'timeline' => 'within_3_months', 'best_time' => 'morning',
                'location' => ['suburb' => 'Mosman'],
            ],
        ])->assertStatus(429);
    }

    public function test_a04_sign_in_locks_out_after_repeated_failures(): void
    {
        auth()->logout();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'helen@example.com', 'password' => 'wrong']);
        }

        $this->post('/login', ['email' => 'helen@example.com', 'password' => 'wrong'])
            ->assertSessionHasErrors('email');

        $this->assertStringContainsString(
            'Too many',
            session('errors')->first('email'),
        );
    }

    /** A page size is chosen from a list. Otherwise it is a way to ask for the whole table. */
    public function test_a04_a_page_size_cannot_be_dictated_by_the_request(): void
    {
        foreach (range(1, 30) as $i) {
            Enquiry::create(['name' => "E{$i}", 'email' => "e{$i}@example.com", 'consented' => true]);
        }

        $this->get('/cms/enquiries?show=all&per_page=100000')->assertOk()->assertInertia(
            fn ($page) => $this->assertCount(25, $page->toArray()['props']['enquiries']),
        );
    }

    // ------------------------------------------------------------ A05: Security misconfiguration

    public function test_a05_every_response_carries_the_security_headers(): void
    {
        foreach (['/', '/cms', '/login'] as $path) {
            $response = $this->get($path);

            $response->assertHeader('X-Content-Type-Options', 'nosniff');
            $response->assertHeader('X-Frame-Options', 'DENY');
            $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
            $this->assertNotNull($response->headers->get('Permissions-Policy'), $path);
            $this->assertNotNull($response->headers->get('Content-Security-Policy'), $path);
        }
    }

    /**
     * HSTS is the one header that is conditional. Over plain HTTP a browser ignores it anyway, and
     * sending it would pin a developer's machine to a scheme it is not serving — so it appears only
     * once the request is secure, which is exactly when it means something.
     */
    public function test_a05_hsts_is_sent_over_https_and_withheld_over_http(): void
    {
        $this->get('http://localhost/')->assertHeaderMissing('Strict-Transport-Security');

        $secure = $this->get('https://localhost/');

        $secure->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        $this->assertStringContainsString('upgrade-insecure-requests', $secure->headers->get('Content-Security-Policy'));
    }

    public function test_a05_the_policy_blocks_the_things_a_policy_is_for(): void
    {
        $policy = $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("object-src 'none'", $policy);
        $this->assertStringContainsString("frame-ancestors 'none'", $policy);
        $this->assertStringContainsString("base-uri 'self'", $policy);
        $this->assertStringContainsString("form-action 'self'", $policy);

        /* The point of the nonce: inline script is not blanket-permitted. */
        $this->assertStringContainsString('nonce-', $policy);
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $policy);
    }

    /**
     * A signed upload is sent by the browser, to storage, from a CMS screen. The policy has to permit
     * that origin or the upload cannot happen at all — and it must permit the origin only, since a
     * CSP source carrying `/bucket` matches by path prefix.
     */
    public function test_a05_the_policy_permits_the_upload_it_signs(): void
    {
        config(['filesystems.disks.s3.url' => null]);
        config(['filesystems.disks.s3.endpoint' => 'http://localhost:4566']);

        $admin = $this->get('/cms/media')->headers->get('Content-Security-Policy');

        $this->assertMatchesRegularExpression(
            '/connect-src [^;]*\bhttp:\/\/localhost:4566\b/',
            $admin,
        );
        $this->assertStringNotContainsString('localhost:4566/', $admin);

        /* Neither the public site nor sign-in uploads, so neither is given the origin. */
        foreach (['/', '/login'] as $path) {
            auth()->logout();

            $this->assertStringNotContainsString(
                'localhost:4566',
                $this->get($path)->headers->get('Content-Security-Policy'),
                $path,
            );
        }
    }

    /**
     * The two cases either side of this one read the policy against a configured origin. This one asks
     * the harder question: is the origin the policy permits the origin `sign()` actually hands the
     * browser? They would both still pass if the signed host moved, which is the failure that shipped
     * — a policy and an upload that were each correct about a different address.
     */
    public function test_a05_the_policy_permits_the_host_the_signed_url_points_at(): void
    {
        /* Not `Storage::fake('s3')`: a fake returns a fake URL, and the whole point here is the real
           host the real signer produces. */
        $signed = $this->postJson('/cms/media/sign', ['name' => 'upload.jpg', 'size' => 2048]);

        $signed->assertOk();

        $parts = parse_url($signed->json('url'));

        $this->assertNotEmpty($parts['host'] ?? null, 'The signed URL carried no host.');

        $origin = ($parts['scheme'] ?? 'https').'://'.$parts['host']
            .(isset($parts['port']) ? ':'.$parts['port'] : '');

        $policy = $this->get('/cms/media')->headers->get('Content-Security-Policy');

        preg_match('/connect-src ([^;]*)/', $policy, $found);

        $this->assertContains(
            $origin,
            preg_split('/\s+/', trim($found[1] ?? '')),
            "The policy does not permit {$origin}, which is where the signed upload is sent.",
        );
    }

    /** A bucket behind a CDN is signed against that host, so the raw endpoint would be the wrong grant. */
    public function test_a05_a_cdn_host_wins_over_the_raw_endpoint(): void
    {
        config(['filesystems.disks.s3.endpoint' => 'http://localhost:4566']);
        config(['filesystems.disks.s3.url' => 'https://media.example.com/spa-media']);

        $policy = $this->get('/cms/media')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString('https://media.example.com', $policy);
        $this->assertStringNotContainsString('media.example.com/spa-media', $policy);
        $this->assertStringNotContainsString('localhost:4566', $policy);
    }

    /**
     * `img-src` used to end in `https:`, which permits a request to any host on the internet. An image
     * needs no response to have already sent its query string, so that was an open exfiltration
     * channel and the widest thing this policy allowed — wider than anything `connect-src` was
     * carefully restricting. `Html` refuses a remote image on the way in so the two agree.
     */
    public function test_a05_an_image_may_not_be_fetched_from_anywhere_at_all(): void
    {
        foreach (['/', '/cms/media'] as $path) {
            preg_match('/img-src ([^;]*)/', (string) $this->get($path)->headers->get('Content-Security-Policy'), $found);

            $sources = preg_split('/\s+/', trim($found[1] ?? ''));

            $this->assertContains("'self'", $sources, $path);
            $this->assertNotContains('https:', $sources, "{$path} still permits any HTTPS host");
            $this->assertNotContains('http:', $sources, $path);
            $this->assertNotContains('*', $sources, $path);
        }
    }

    /** Analytics measures some things with a pixel, and the blanket `https:` used to cover it. */
    public function test_a05_analytics_pixels_are_named_now_that_the_scheme_is_gone(): void
    {
        $this->put('/cms/settings', [
            'name' => 'Seniors Property Advisors',
            'tracking' => ['ga4' => 'G-ABCDE12345', 'gtm' => null],
        ])->assertRedirect();

        preg_match('/img-src ([^;]*)/', (string) $this->get('/')->headers->get('Content-Security-Policy'), $found);

        $this->assertStringContainsString('google-analytics.com', $found[1] ?? '');
    }

    /** A policy that permits an analytics vendor on a site with no analytics is one nobody has read. */
    public function test_a05_third_parties_are_only_allowed_when_they_are_actually_used(): void
    {
        $this->assertStringNotContainsString(
            'googletagmanager',
            $this->get('/')->headers->get('Content-Security-Policy'),
        );

        $this->put('/cms/settings', [
            'name' => 'Seniors Property Advisors',
            'tracking' => ['ga4' => 'G-ABCDE12345', 'gtm' => null],
        ])->assertRedirect();

        $this->assertStringContainsString(
            'googletagmanager',
            $this->get('/')->headers->get('Content-Security-Policy'),
        );
    }

    public function test_a05_the_admin_is_never_offered_to_a_search_engine(): void
    {
        $this->assertStringContainsString('Disallow: /cms', $this->get('/robots.txt')->getContent());

        auth()->logout();
        $this->assertStringContainsString(
            'noindex',
            $this->get('/login')->viewData('page')['props']['head']['robots'],
        );
    }

    /**
     * `public/hot` is a real file on a developer's machine whenever `npm run dev` is running, and the
     * deployment check is right to fail on it — so the tests that assert a *passing* check have to say
     * where to look, or a second terminal decides whether this suite is green.
     */
    private function noDevelopmentBuildMarker(): void
    {
        app(Vite::class)->useHotFile(storage_path('framework/testing/absent-hot'));
    }

    public function test_a05_the_deployment_check_fails_on_a_production_misconfiguration(): void
    {
        $this->noDevelopmentBuildMarker();
        config(['app.debug' => true, 'session.secure' => false]);

        $this->artisan('security:check --production')->assertFailed();

        config([
            'app.debug' => false,
            'session.secure' => true,
            'app.url' => 'https://example.com',
            /* An https site with nothing in front of it is a misconfiguration in its own right: the
               limits key on an address the proxy has replaced, and HSTS never leaves the building. */
            'app.trusted_proxies' => '10.0.0.0/8',
            'filesystems.disks.s3.bucket' => 'spa-media',
            'filesystems.disks.s3.key' => 'AKIAREAL',
            /* Explicitly, because the suite reads the developer's own `.env` — which points storage at
               the local emulator, so without this line the check would fail here for a reason that has
               nothing to do with what this test is about. */
            'filesystems.disks.s3.endpoint' => null,
        ]);

        $this->artisan('security:check --production')->assertSuccessful();
    }

    /**
     * The one misconfiguration that looks like a working site.
     *
     * `.env.example` points storage at floci, the S3 emulator `docker-compose.yml` runs for a
     * developer's machine, and copying that file to a server instead of `.env.production.example`
     * carries the endpoint with it. Every upload then lands in a container's volume — no versioning,
     * no backup — and nothing complains, because the disk is configured not to throw. So the check has
     * to be the thing that complains.
     */
    public function test_a05_the_deployment_check_refuses_a_local_storage_emulator(): void
    {
        $this->noDevelopmentBuildMarker();
        config([
            'app.debug' => false,
            'session.secure' => true,
            'app.url' => 'https://example.com',
            'app.trusted_proxies' => '10.0.0.0/8',
            'filesystems.disks.s3.bucket' => 'spa-media',
            'filesystems.disks.s3.key' => 'AKIAREAL',
        ]);

        foreach (['http://localhost:4566', 'http://127.0.0.1:4566', 'http://host.docker.internal:4566'] as $endpoint) {
            config(['filesystems.disks.s3.endpoint' => $endpoint]);
            $this->artisan('security:check --production')->assertFailed();
        }

        /* A real hostname is not a defence: this is the shape a staging box takes when somebody runs
           the emulator on the server itself, and it is indistinguishable from working. */
        config(['filesystems.disks.s3.endpoint' => 'https://spa.example.com:4566']);
        $this->artisan('security:check --production')->assertFailed();

        /* Unset is the production value, and a real endpoint — object storage that is not AWS — is a
           supported choice this must not refuse. */
        config(['filesystems.disks.s3.endpoint' => null]);
        $this->artisan('security:check --production')->assertSuccessful();

        config(['filesystems.disks.s3.endpoint' => 'https://syd1.digitaloceanspaces.com']);
        $this->artisan('security:check --production')->assertSuccessful();
    }

    /**
     * The seed is development data, and one of its seeders matches on email.
     *
     * `db:seed --force` on a server is a plausible keystroke — after a restore, or from somebody
     * following a README — and `UserSeeder` uses `updateOrCreate`, so it would not merely add a test
     * account. It would reset the live site administrator's password to a well-known word. The
     * docblocks said "local development accounts only" for a long time and nothing enforced it.
     */
    public function test_a05_the_development_seeders_refuse_to_run_on_a_live_site(): void
    {
        $email = 'superadmin@seniorspropertyadvisors.com.au';

        (new UserSeeder)->run();
        $this->assertTrue(Hash::check('password', User::where('email', $email)->value('password')));

        User::where('email', $email)->update(['password' => Hash::make('a-real-chosen-password')]);

        $this->app['env'] = 'production';

        (new UserSeeder)->run();
        (new SampleContentSeeder)->run();

        $this->assertTrue(
            Hash::check('a-real-chosen-password', User::where('email', $email)->value('password')),
            'the development seeder overwrote a real account on a production environment',
        );
        $this->assertSame(0, BlogPost::count());
    }

    public function test_a05_the_deployment_check_fails_when_uploads_have_nowhere_to_go(): void
    {
        config([
            'app.debug' => false,
            'session.secure' => true,
            'app.url' => 'https://example.com',
            'app.trusted_proxies' => '10.0.0.0/8',
            'filesystems.disks.s3.bucket' => null,
            'filesystems.disks.s3.key' => null,
        ]);

        $this->artisan('security:check --production')->assertFailed();
    }

    // ------------------------------------------------- A06: Vulnerable and outdated components

    /**
     * `composer audit` and `npm audit` are the real check and they are run by hand — a test cannot
     * reach the advisory database offline. What is pinned here is that the lock files exist and are
     * committed, since an unlocked dependency tree cannot be audited at all.
     */
    public function test_a06_dependencies_are_locked(): void
    {
        $this->assertFileExists(base_path('composer.lock'));
        $this->assertFileExists(base_path('package-lock.json'));
    }

    // ------------------------------------------- A07: Identification and authentication failures

    public function test_a07_a_wrong_password_and_an_unknown_account_answer_identically(): void
    {
        auth()->logout();
        $this->clientAdmin(['email' => 'known@example.com']);

        $message = function (string $email): string {
            $errors = $this->post('/login', ['email' => $email, 'password' => 'wrong'])
                ->assertSessionHasErrors('email')
                ->getSession()->get('errors');

            /* A MessageBag once flashed, a plain array once it has been read back. */
            return is_array($errors) ? $errors['email'][0] : $errors->getBag('default')->first('email');
        };

        /* Different answers here are how an attacker learns which addresses have accounts. */
        $this->assertSame($message('known@example.com'), $message('nobody@example.com'));
    }

    public function test_a07_signing_in_replaces_the_session_identifier(): void
    {
        auth()->logout();
        $this->clientAdmin(['email' => 'fixation@example.com']);

        $this->get('/login');
        $before = session()->getId();

        $this->post('/login', ['email' => 'fixation@example.com', 'password' => 'password']);

        $this->assertNotSame($before, session()->getId(), 'a fixed session id would survive sign-in');
    }

    public function test_a07_a_deactivated_account_cannot_sign_in(): void
    {
        auth()->logout();
        $this->clientAdmin(['email' => 'gone@example.com', 'is_active' => false]);

        $this->post('/login', ['email' => 'gone@example.com', 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a07_a_short_password_is_refused(): void
    {
        $this->patch('/cms/account/password', [
            'current_password' => 'password',
            'password' => 'short', 'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');
    }

    // ------------------------------------------ A08: Software and data integrity failures

    /** An upload is trusted by its bytes, never by the name the browser attached to it. */
    public function test_a08_a_file_is_judged_by_its_bytes_not_its_name(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('2026/08/lie.jpg', '<?php echo "not an image";');

        $this->postJson('/cms/media', ['key' => '2026/08/lie.jpg', 'name' => 'lie.jpg', 'mime' => 'image/jpeg'])
            ->assertStatus(422);

        $this->assertFalse(Storage::disk('s3')->exists('2026/08/lie.jpg'));
        $this->assertSame(0, Media::count());
    }

    public function test_a08_an_upload_key_must_be_one_this_application_minted(): void
    {
        Storage::fake('s3');

        foreach (['../../etc/passwd', '/etc/passwd', 'somebody-elses/file.jpg'] as $key) {
            $this->postJson('/cms/media', ['key' => $key, 'name' => 'x.jpg', 'mime' => 'image/jpeg'])
                ->assertStatus(422);
        }
    }

    // ------------------------------------- A09: Security logging and monitoring failures

    public function test_a09_content_changes_are_recorded_against_the_person_who_made_them(): void
    {
        $user = $this->superAdmin(['name' => 'Recorded Person']);
        $this->actingAs($user);

        BlogPost::create(['slug' => 'logged', 'title' => 'Logged', 'body' => '<p>b</p>', 'status' => 'draft']);

        $this->assertDatabaseHas('activity_log', [
            'action' => 'created', 'subject_type' => 'BlogPost', 'by_id' => $user->id, 'by_name' => 'Recorded Person',
        ]);
    }

    /** Erasing somebody while minting a permanent copy of their name is not erasing them. */
    public function test_a09_the_log_does_not_keep_what_a_deletion_was_meant_to_remove(): void
    {
        $enquiry = Enquiry::create(['name' => 'Erase Me', 'email' => 'erase@example.com', 'consented' => true]);

        $this->delete("/cms/enquiries/{$enquiry->id}")->assertRedirect();

        $this->assertDatabaseHas('activity_log', ['subject_type' => 'Enquiry', 'action' => 'deleted']);
        $this->assertDatabaseMissing('activity_log', ['subject_label' => 'Erase Me']);
    }

    // ------------------------------------------------------ A10: Server-side request forgery

    /**
     * One endpoint in this application makes an outbound request on a visitor's behalf, and the
     * visitor supplies part of the address. That is the whole SSRF surface.
     */
    public function test_a10_a_visitor_cannot_steer_the_outbound_request(): void
    {
        Http::fake(['places.googleapis.com/*' => Http::response([])]);

        auth()->logout();

        foreach (['../places:autocomplete', '../../../etc/passwd', 'http://169.254.169.254/latest/meta-data/'] as $payload) {
            $this->getJson('/api/suburbs?place_id='.urlencode($payload))->assertOk();
        }

        Http::assertSent(function ($request) {
            /* The host is what matters. A payload may still appear in the address — encoded, as
               one literal path segment under /v1/places/ — and that is the fix working, not
               failing: it can no longer climb out of the path or change where the request goes. */
            $this->assertSame('places.googleapis.com', parse_url($request->url(), PHP_URL_HOST));
            $this->assertStringStartsWith('/v1/places/', parse_url($request->url(), PHP_URL_PATH));
            $this->assertStringNotContainsString('/../', $request->url());
            $this->assertStringNotContainsString('places:autocomplete', parse_url($request->url(), PHP_URL_PATH));

            return true;
        });
    }

    public function test_a10_the_api_key_never_reaches_the_browser(): void
    {
        config(['services.google.places_key' => 'secret-key-value']);

        Http::fake(['places.googleapis.com/*' => Http::response([])]);

        auth()->logout();

        $body = $this->getJson('/api/suburbs?q=hawthorn')->assertOk()->getContent();

        $this->assertStringNotContainsString('secret-key-value', $body);
        $this->assertStringNotContainsString('secret-key-value', $this->get('/')->getContent());
    }
}
