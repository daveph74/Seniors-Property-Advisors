<?php

namespace Tests\Feature;

use App\Content\Site;
use App\Models\Activity;
use App\Models\BlogPost;
use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use Tests\TestCase;

/**
 * The two things the SEO overview may write, and everything it may not.
 *
 * The screen exists so twenty missing descriptions do not mean twenty trips into the builder, which
 * makes it a second way into fields the builder already owns. Two rules keep that safe: a patch
 * carries only the keys it changes, and the fields most able to cost a page its traffic are not
 * offered here at all.
 */
class SeoEditingTest extends TestCase
{
    private function article(array $overrides = []): BlogPost
    {
        return BlogPost::create(array_merge([
            'slug' => 'an-article',
            'title' => 'An article',
            'body' => '<p>Words.</p>',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ], $overrides));
    }

    private function page(string $slug = 'faqs'): Page
    {
        return Page::where('slug', $slug)->firstOrFail();
    }

    public function test_a_description_can_be_written_for_a_page_and_reaches_the_delivered_html(): void
    {
        $page = $this->page();

        $this->patch('/cms/seo/page/'.$page->cms_id, ['description' => 'What this page is about.'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('What this page is about.', $page->fresh()->seo['description']);

        $this->assertStringContainsString(
            'name="description" content="What this page is about."',
            $this->get('/faqs')->assertOk()->getContent(),
        );
    }

    public function test_a_description_can_be_written_for_an_article(): void
    {
        $article = $this->article();

        $this->patch('/cms/seo/article/'.$article->id, ['description' => 'What this article says.'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('What this article says.', $article->fresh()->seo['description']);
    }

    /**
     * The rule the testimonials screen already pays for: send the whole record on a single-field
     * change and a stale copy reverts whatever moved between the screen loading and the save. Here
     * the neighbours are a canonical and a search title the screen does not even show, so it must
     * patch one field and leave the rest untouched.
     */
    public function test_patching_a_description_leaves_the_title_and_canonical_alone(): void
    {
        $page = $this->page();
        $page->update(['seo' => [
            'title' => 'A carefully chosen title',
            'canonical' => 'https://example.com/elsewhere',
            'noindex' => true,
        ]]);

        $this->patch('/cms/seo/page/'.$page->cms_id, ['description' => 'Added later.'])->assertRedirect();

        $seo = $page->fresh()->seo;

        $this->assertSame('A carefully chosen title', $seo['title']);
        $this->assertSame('https://example.com/elsewhere', $seo['canonical']);
        $this->assertTrue($seo['noindex']);
        $this->assertSame('Added later.', $seo['description']);
    }

    public function test_the_page_title_is_not_touched_by_an_seo_patch(): void
    {
        $page = $this->page();
        $before = $page->title;

        $this->patch('/cms/seo/page/'.$page->cms_id, ['noindex' => true])->assertRedirect();

        $this->assertSame($before, $page->fresh()->title);
    }

    /**
     * Un-hiding has to overwrite the stored true. A false filtered out as "empty" would merge the
     * old value straight back and the switch would appear not to work.
     */
    public function test_a_page_can_be_hidden_and_shown_again(): void
    {
        $page = $this->page();

        $this->patch('/cms/seo/page/'.$page->cms_id, ['noindex' => true])->assertRedirect();
        $this->assertTrue($page->fresh()->seo['noindex']);
        $this->get('/sitemap.xml')->assertDontSee('<loc>'.url('/faqs').'</loc>', false);

        $this->patch('/cms/seo/page/'.$page->cms_id, ['noindex' => false])->assertRedirect();
        $this->assertFalse($page->fresh()->seo['noindex'] ?? false);
        $this->get('/sitemap.xml')->assertSee('<loc>'.url('/faqs').'</loc>', false);
    }

    /** Clearing it hands the address back to the site default rather than leaving it blank. */
    public function test_clearing_a_description_falls_back_to_the_site_default(): void
    {
        Setting::updateOrCreate(
            ['key' => Site::KEY],
            ['value' => array_replace(Site::all(), ['seo' => ['description' => 'The site-wide one.']])],
        );

        $page = $this->page();
        $page->update(['seo' => ['description' => 'Its own.']]);

        $this->patch('/cms/seo/page/'.$page->cms_id, ['description' => ''])->assertRedirect();

        $this->assertArrayNotHasKey('description', $page->fresh()->seo);
        $this->assertStringContainsString(
            'content="The site-wide one."',
            $this->get('/faqs')->assertOk()->getContent(),
        );
    }

    public function test_a_description_longer_than_the_stored_limit_is_refused(): void
    {
        $page = $this->page();
        $before = $page->seo['description'] ?? null;

        $this->patch('/cms/seo/page/'.$page->cms_id, ['description' => str_repeat('a', 321)])
            ->assertSessionHasErrors('description');

        /* Unchanged rather than absent: this page ships with a description, and a refusal that
           emptied it would be worse than one that saved the long value. */
        $this->assertSame($before, $page->fresh()->seo['description'] ?? null);
    }

    /**
     * The limit is shared with the builder's own request, so a description that saves from one screen
     * cannot be refused by the other — which would read as one of the two being broken.
     */
    public function test_the_limit_is_the_same_one_the_builder_enforces(): void
    {
        $page = $this->page();
        $long = str_repeat('b', 321);

        $this->patch('/cms/seo/page/'.$page->cms_id, ['description' => $long])
            ->assertSessionHasErrors('description');

        $this->patch('/cms/pages/'.$page->cms_id.'/details', [
            'title' => $page->title,
            'seo' => ['description' => $long],
        ])->assertSessionHasErrors('seo.description');
    }

    public function test_a_patch_carrying_nothing_is_refused_rather_than_recorded_as_a_change(): void
    {
        $this->patch('/cms/seo/page/'.$this->page()->cms_id, [])->assertStatus(422);
    }

    /**
     * A trashed article is listed — an address a search engine still holds is what somebody comes
     * here to explain — and it is not writable. The screen hides the editor for one, and this is
     * the half that does not depend on the screen remembering to: found because a full browser run
     * left a deleted article at the top of the list, its editor opened, and the save answered 404
     * while the panel sat there looking as though it were still working.
     */
    public function test_a_deleted_article_cannot_be_edited_from_here(): void
    {
        $article = $this->article();
        $article->delete();

        $this->patch('/cms/seo/article/'.$article->id, ['description' => 'Written after deletion.'])
            ->assertNotFound();

        $this->assertNull($article->fresh()->seo['description'] ?? null);
    }

    public function test_an_unknown_kind_is_not_a_route(): void
    {
        $this->patch('/cms/seo/testimonial/1', ['noindex' => true])->assertNotFound();
    }

    public function test_the_site_defaults_can_be_saved_and_are_recorded(): void
    {
        $this->put('/cms/seo/defaults', [
            'titleFormat' => '{title} · {site}',
            'description' => 'Independent property advice.',
            'image' => '/media/2026/08/share.jpg',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $seo = Setting::find(Site::KEY)->value['seo'];

        $this->assertSame('{title} · {site}', $seo['titleFormat']);
        $this->assertSame('Independent property advice.', $seo['description']);
        $this->assertTrue(Activity::where('subject_label', 'SEO defaults')->exists());
    }

    /**
     * The row has two writers now — this screen and `/cms/settings` — under two abilities, and a
     * wholesale write from either would erase the other's half. Losing the analytics ids that way
     * fails silently until a monthly report comes back empty.
     */
    public function test_saving_the_defaults_leaves_the_tracking_ids_and_legal_wording_alone(): void
    {
        Setting::updateOrCreate(
            ['key' => Site::KEY],
            ['value' => array_replace(Site::all(), [
                'tracking' => ['ga4' => 'G-REALONE12', 'gtm' => null],
                'legal' => ['disclaimer' => 'Careful wording.', 'privacyPage' => null],
            ])],
        );

        $this->put('/cms/seo/defaults', [
            'titleFormat' => '{title} | {site}',
            'description' => null,
            'image' => null,
        ])->assertRedirect();

        $site = Setting::find(Site::KEY)->value;

        $this->assertSame('G-REALONE12', $site['tracking']['ga4']);
        $this->assertSame('Careful wording.', $site['legal']['disclaimer']);
    }

    /** And the same in reverse: a Settings save must not take the SEO defaults with it. */
    public function test_saving_settings_leaves_the_seo_defaults_alone(): void
    {
        $this->put('/cms/seo/defaults', [
            'titleFormat' => '{title} :: {site}',
            'description' => 'Chosen on the SEO screen.',
            'image' => null,
        ])->assertRedirect();

        $this->put('/cms/settings', [
            'name' => 'Seniors Property Advisors',
            'favicon' => null,
            'social' => ['facebook' => null, 'linkedin' => null],
            'tracking' => ['ga4' => null, 'gtm' => null],
            'legal' => ['disclaimer' => null, 'privacyPage' => null],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $seo = Setting::find(Site::KEY)->value['seo'];

        $this->assertSame('{title} :: {site}', $seo['titleFormat']);
        $this->assertSame('Chosen on the SEO screen.', $seo['description']);
    }

    public function test_an_svg_cannot_be_the_default_sharing_image(): void
    {
        $this->put('/cms/seo/defaults', [
            'titleFormat' => null,
            'description' => null,
            'image' => '/media/2026/08/logo.svg',
        ])->assertSessionHasErrors('image');
    }

    public function test_a_client_administrator_may_edit_here_and_still_cannot_reach_settings(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::CLIENT_ADMIN, 'is_active' => true]));

        $this->patch('/cms/seo/page/'.$this->page()->cms_id, ['description' => 'By the client admin.'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->put('/cms/seo/defaults', ['titleFormat' => '{title} | {site}', 'description' => null, 'image' => null])
            ->assertRedirect();

        /* The point of the separate ability: opening SEO up did not open the analytics ids or the
           legal wording with it. */
        $this->get('/cms/settings')->assertForbidden();
        $this->put('/cms/settings', ['name' => 'Renamed'])->assertForbidden();
    }

    public function test_a_signed_out_visitor_cannot_write_anything_here(): void
    {
        auth()->logout();

        $this->patch('/cms/seo/page/1', ['noindex' => true])->assertRedirect('/login');
        $this->put('/cms/seo/defaults', ['titleFormat' => null])->assertRedirect('/login');
    }
}
