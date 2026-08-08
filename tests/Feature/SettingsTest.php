<?php

namespace Tests\Feature;

use App\Content\Site;
use App\Models\Activity;
use App\Models\BlogPost;
use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    private function site(): array
    {
        return Setting::find(Site::KEY)->value;
    }

    private function save(array $overrides = []): TestResponse
    {
        return $this->put('/cms/settings', array_merge([
            'name' => 'Seniors Property Advisors',
            'favicon' => null,
            'seo' => ['titleFormat' => '{title} | {site}', 'description' => null, 'image' => null],
            'social' => ['facebook' => null, 'linkedin' => null],
            'tracking' => ['ga4' => null, 'gtm' => null],
            'legal' => ['disclaimer' => null, 'privacyPage' => null],
        ], $overrides));
    }

    /**
     * updateOrCreate rather than create: some of these slugs — privacy-policy among them — are
     * seeded content now, and a test that insists on minting its own copy hits the unique index
     * the moment the site grows the page it was pretending to have.
     */
    private function page(string $slug, string $status = 'published'): Page
    {
        $page = Page::firstOrNew(['slug' => $slug]);

        $page->fill([
            'cms_id' => $page->cms_id ?? (Page::max('cms_id') ?? 0) + 1,
            'url' => '/'.$slug,
            'title' => ucfirst(str_replace('-', ' ', $slug)),
            'status' => $status,
            'published' => [['id' => 'a', 'type' => 'hero', 'active' => true, 'data' => []]],
        ])->save();

        return $page;
    }

    public function test_the_screen_loads_the_stored_settings(): void
    {
        $this->get('/cms/settings')->assertOk()->assertInertia(function ($page) {
            $settings = $page->toArray()['props']['settings'];

            $this->assertSame('Seniors Property Advisors', $settings['name']);
            $this->assertSame('{title} | {site}', $settings['seo']['titleFormat']);
        });
    }

    public function test_the_title_pattern_reaches_the_delivered_html(): void
    {
        $this->page('services');
        $this->save(['seo' => [
            'titleFormat' => '{title} — {site}',
            'description' => null,
            'image' => null,
        ]])->assertRedirect()->assertSessionHasNoErrors();

        $html = $this->get('/services')->assertOk()->getContent();

        $this->assertStringContainsString('<title inertia>Services — Seniors Property Advisors</title>', $html);
    }

    public function test_a_title_already_carrying_the_name_is_left_alone(): void
    {
        $page = $this->page('about');
        $page->update(['title' => 'About Seniors Property Advisors']);

        $this->save()->assertRedirect();

        $html = $this->get('/about')->assertOk()->getContent();

        /* Otherwise every shared link and search result reads "… Advisors | Seniors Property
           Advisors", which is how a title pattern usually goes wrong. */
        $this->assertStringContainsString('<title inertia>About Seniors Property Advisors</title>', $html);
        $this->assertStringNotContainsString('Advisors | Seniors', $html);
    }

    public function test_the_default_description_fills_in_for_a_page_that_has_none(): void
    {
        $this->page('services');
        $this->save(['seo' => [
            'titleFormat' => null,
            'description' => 'Independent property advice for older Australians.',
            'image' => null,
        ]])->assertRedirect();

        $html = $this->get('/services')->assertOk()->getContent();

        $this->assertStringContainsString(
            'name="description" content="Independent property advice for older Australians."',
            $html,
        );
    }

    public function test_a_page_with_its_own_description_keeps_it(): void
    {
        $page = $this->page('services');
        $page->update(['seo' => ['description' => 'What this page says.']]);

        $this->save(['seo' => [
            'titleFormat' => null,
            'description' => 'The site-wide one.',
            'image' => null,
        ]])->assertRedirect();

        $html = $this->get('/services')->assertOk()->getContent();

        $this->assertStringContainsString('content="What this page says."', $html);
        $this->assertStringNotContainsString('The site-wide one.', $html);
    }

    public function test_the_default_sharing_image_stands_in_and_is_absolute(): void
    {
        $this->page('services');
        $this->save(['seo' => [
            'titleFormat' => null,
            'description' => null,
            'image' => '/media/2026/08/share.jpg',
        ]])->assertRedirect();

        $html = $this->get('/services')->assertOk()->getContent();

        /* Open Graph needs an absolute URL; a /media path is silently useless to a crawler. */
        $this->assertStringContainsString(
            'property="og:image" content="http://localhost/media/2026/08/share.jpg"',
            $html,
        );
    }

    public function test_an_article_prefers_its_own_picture_over_the_site_default(): void
    {
        $this->save(['seo' => [
            'titleFormat' => null,
            'description' => null,
            'image' => '/media/2026/08/share.jpg',
        ]])->assertRedirect();

        $this->post('/cms/blog', [
            'title' => 'Planning a downsize',
            'summary' => 'Where to start.',
            'body' => '<p>Words.</p>',
            'featured_image' => '/media/2026/08/hero.jpg',
        ])->assertRedirect();

        BlogPost::sole()->update(['status' => 'published', 'published_at' => now()]);

        $html = $this->get('/blog/planning-a-downsize')->assertOk()->getContent();

        $this->assertStringContainsString('/media/2026/08/hero.jpg', $html);
        $this->assertStringNotContainsString('share.jpg', $html);
    }

    public function test_the_website_name_becomes_the_publisher_in_structured_data(): void
    {
        $this->save(['name' => 'Agent Finder Australia'])->assertRedirect();

        $this->post('/cms/blog', [
            'title' => 'Planning a downsize',
            'summary' => 'Where to start.',
            'body' => '<p>Words.</p>',
        ])->assertRedirect();

        BlogPost::sole()->update(['status' => 'published', 'published_at' => now()]);

        $html = $this->get('/blog/planning-a-downsize')->assertOk()->getContent();

        /* It was hardcoded, so renaming the business left the wrong publisher in every article. */
        $this->assertStringContainsString('"name":"Agent Finder Australia"', $html);
    }

    public function test_the_favicon_is_linked_only_once_one_is_chosen(): void
    {
        $this->page('services');

        $this->assertStringNotContainsString('rel="icon"', $this->get('/services')->getContent());

        $this->save(['favicon' => '/media/2026/08/icon.png'])->assertRedirect();

        $this->assertStringContainsString(
            '<link rel="icon" href="/media/2026/08/icon.png" />',
            $this->get('/services')->getContent(),
        );
    }

    public function test_an_analytics_id_has_to_look_like_one(): void
    {
        /* This is printed inside a <script>. Blade's escaping means an injected statement lands as
           inert text rather than running, but an unchecked id still puts a broken analytics tag on
           every page — and the format check is what keeps that guarantee from resting on escaping. */
        $this->save(['tracking' => ['ga4' => "G-1';alert(1);//", 'gtm' => null]])
            ->assertSessionHasErrors('tracking.ga4');

        $this->save(['tracking' => ['ga4' => null, 'gtm' => 'not-an-id']])
            ->assertSessionHasErrors('tracking.gtm');
    }

    public function test_a_rogue_id_stored_by_hand_still_cannot_reach_the_page(): void
    {
        Setting::updateOrCreate(['key' => Site::KEY], ['value' => [
            'tracking' => ['ga4' => "G-1';alert(1);//", 'gtm' => null],
        ]]);

        $this->page('services');

        $html = $this->get('/services')->assertOk()->getContent();

        /* The second guard, in `Site::tracking()`, for a row that never went through validation. */
        $this->assertStringNotContainsString('alert(1)', $html);
        $this->assertStringNotContainsString('gtag/js', $html);
    }

    public function test_analytics_loads_for_a_reader_and_not_inside_the_admin(): void
    {
        $this->page('services');
        $this->save(['tracking' => ['ga4' => 'G-ABC1234567', 'gtm' => null]])->assertRedirect();

        $this->assertStringContainsString('gtag/js?id=G-ABC1234567', $this->get('/services')->getContent());

        /* Measuring the admin would count the people editing the site as visitors, and load a
           third-party script for staff who never asked for one. The id itself is in this page's
           form, of course — it is the script that must not be there. */
        $this->assertStringNotContainsString('gtag/js', $this->get('/cms/settings')->getContent());
    }

    public function test_the_disclaimer_reaches_the_footer(): void
    {
        $this->page('services');
        $this->save(['legal' => [
            'disclaimer' => 'General guidance only, not financial advice.',
            'privacyPage' => null,
        ]])->assertRedirect();

        $this->get('/services')->assertOk()->assertInertia(function ($page) {
            $this->assertSame(
                'General guidance only, not financial advice.',
                $page->toArray()['props']['site']['disclaimer'],
            );
        });
    }

    public function test_only_a_published_page_can_be_the_privacy_policy(): void
    {
        $draft = $this->page('privacy-policy', 'draft');

        $this->save(['legal' => ['disclaimer' => null, 'privacyPage' => $draft->id]])
            ->assertSessionHasErrors('legal.privacyPage');
    }

    public function test_the_privacy_page_becomes_a_link_readers_can_follow(): void
    {
        $privacy = $this->page('privacy-policy');
        $this->page('services');

        $this->save(['legal' => ['disclaimer' => null, 'privacyPage' => $privacy->id]])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->get('/services')->assertOk()->assertInertia(function ($page) {
            $this->assertSame('/privacy-policy', $page->toArray()['props']['site']['privacyUrl']);
        });
    }

    public function test_a_privacy_page_unpublished_later_stops_being_linked(): void
    {
        $privacy = $this->page('privacy-policy');
        $this->page('services');

        $this->save(['legal' => ['disclaimer' => null, 'privacyPage' => $privacy->id]])->assertRedirect();

        $privacy->update(['status' => 'draft']);

        /* The id stays stored, but the link is resolved per request — otherwise taking the page
           down would leave the consent line pointing at a 404. */
        $this->get('/services')->assertOk()->assertInertia(function ($page) {
            $this->assertNull($page->toArray()['props']['site']['privacyUrl']);
        });
    }

    public function test_social_links_need_a_full_web_address(): void
    {
        $this->save(['social' => ['facebook' => '/facebook', 'linkedin' => null]])
            ->assertSessionHasErrors('social.facebook');
    }

    public function test_a_blank_social_field_is_simply_not_listed(): void
    {
        $this->page('services');
        $this->save(['social' => [
            'facebook' => 'https://facebook.com/spa',
            'linkedin' => null,
        ]])->assertRedirect();

        $this->get('/services')->assertOk()->assertInertia(function ($page) {
            $social = $page->toArray()['props']['site']['social'];

            $this->assertCount(1, $social);
            $this->assertSame('Facebook', $social[0]['label']);
        });
    }

    public function test_the_website_needs_a_name(): void
    {
        $this->save(['name' => ''])->assertSessionHasErrors('name');
    }

    public function test_markup_alone_is_not_a_name(): void
    {
        $this->save(['name' => '<hr>'])->assertSessionHasErrors('name');
    }

    public function test_a_change_is_recorded_against_the_part_that_moved(): void
    {
        $this->save(['tracking' => ['ga4' => 'G-ABC1234567', 'gtm' => null]])->assertRedirect();

        $entry = Activity::where('subject_type', 'Settings')->latest('id')->first();

        $this->assertNotNull($entry);
        $this->assertStringContainsString('Tracking', $entry->subject_label);
    }

    public function test_saving_without_changing_anything_is_not_an_event(): void
    {
        $this->save()->assertRedirect();
        $count = Activity::where('subject_type', 'Settings')->count();

        $this->save()->assertRedirect();

        $this->assertSame($count, Activity::where('subject_type', 'Settings')->count());
    }

    public function test_settings_leave_the_menus_and_wording_alone(): void
    {
        $globals = Setting::find('globals')->value;

        $this->save(['name' => 'Something else'])->assertRedirect();

        /* Its own row precisely so this cannot happen — two screens editing one blob is how a save
           from one reverts the other. */
        $this->assertSame($globals, Setting::find('globals')->value);
    }

    public function test_a_client_administrator_cannot_reach_settings(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::CLIENT_ADMIN, 'is_active' => true]));

        $this->get('/cms/settings')->assertForbidden();
        $this->put('/cms/settings', [])->assertForbidden();
    }

    public function test_a_guest_cannot_reach_settings(): void
    {
        auth()->logout();

        $this->get('/cms/settings')->assertRedirect('/login');
        $this->put('/cms/settings', [])->assertRedirect('/login');
    }
}
