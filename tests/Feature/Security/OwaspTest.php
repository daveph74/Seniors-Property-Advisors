<?php

namespace Tests\Feature\Security;

use App\Models\BlogPost;
use App\Models\Enquiry;
use App\Models\Faq;
use App\Models\Media;
use App\Models\Page;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
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

    public function test_a05_the_deployment_check_fails_on_a_production_misconfiguration(): void
    {
        config(['app.debug' => true, 'session.secure' => false]);

        $this->artisan('security:check --production')->assertFailed();

        config(['app.debug' => false, 'session.secure' => true, 'app.url' => 'https://example.com']);

        $this->artisan('security:check --production')->assertSuccessful();
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
