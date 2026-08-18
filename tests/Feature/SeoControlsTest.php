<?php

namespace Tests\Feature;

use App\Content\PageContentStore;
use App\Models\BlogPost;
use App\Models\Page;
use Tests\TestCase;

class SeoControlsTest extends TestCase
{
    private function page(string $slug = 'about'): Page
    {
        return Page::create([
            'cms_id' => Page::max('cms_id') + 1,
            'slug' => $slug,
            'url' => '/'.$slug,
            'title' => 'About us',
            'status' => 'published',
            'published' => [['id' => 'a', 'type' => 'hero', 'active' => true, 'data' => []]],
        ]);
    }

    private function details(Page $page, array $seo): void
    {
        $this->patch("/cms/pages/{$page->cms_id}/details", [
            'title' => $page->title,
            'seo' => $seo,
        ])->assertRedirect();
    }

    public function test_a_page_can_name_a_different_canonical_address(): void
    {
        $page = $this->page();

        $this->details($page, ['canonical' => 'https://example.com/the-original']);

        $this->get('/about')->assertOk()->assertInertia(
            fn ($p) => $p->where('seo.canonical', 'https://example.com/the-original'),
        );
    }

    public function test_a_canonical_address_has_to_be_a_real_address(): void
    {
        $page = $this->page();

        $this->patch("/cms/pages/{$page->cms_id}/details", [
            'title' => $page->title,
            'seo' => ['canonical' => 'not a url'],
        ])->assertSessionHasErrors('seo.canonical');
    }

    public function test_a_page_can_be_hidden_from_search_engines_and_shown_again(): void
    {
        $page = $this->page();

        $this->details($page, ['noindex' => true]);

        $this->assertTrue((new PageContentStore)->document('about')['seo']['noindex']);

        /* Switching it back off has to reach the stored value rather than merging the old true back
           in. The server always handled that; the panel did not, which is where the fix was. */
        $this->details($page->refresh(), ['noindex' => false]);

        $this->assertFalse((new PageContentStore)->document('about')['seo']['noindex']);
    }

    public function test_hiding_a_page_leaves_it_readable_to_anyone_with_the_link(): void
    {
        $page = $this->page();

        $this->details($page, ['noindex' => true]);

        $this->get('/about')->assertOk()->assertInertia(fn ($p) => $p->where('seo.noindex', true));
    }

    public function test_an_article_carries_its_canonical_and_indexing_choice(): void
    {
        $this->post('/cms/blog', [
            'title' => 'Syndicated piece',
            'body' => '<p>Words.</p>',
            'seo' => ['canonical' => 'https://example.com/first-published-here', 'noindex' => true],
        ])->assertRedirect();

        $seo = BlogPost::sole()->seo;

        $this->assertSame('https://example.com/first-published-here', $seo['canonical']);
        $this->assertTrue($seo['noindex']);
    }

    public function test_an_ordinary_article_carries_no_indexing_instruction_at_all(): void
    {
        $this->post('/cms/blog', ['title' => 'Ordinary', 'body' => '<p>Words.</p>'])->assertRedirect();

        $this->assertArrayNotHasKey('noindex', BlogPost::sole()->seo);
    }

    /**
     * These assert the *delivered HTML*, not the Inertia props. Every one of these tags used to be
     * added by JavaScript, so a link scraper — Facebook, LinkedIn, X, WhatsApp, Slack — received a
     * document with no image, no description and the site title on every page. Props being right is
     * what made that invisible, which is why these read the markup.
     */
    public function test_the_sharing_tags_reach_a_scraper_that_runs_no_javascript(): void
    {
        $page = $this->page('services');

        $this->details($page, ['title' => 'What we do', 'description' => 'Independent advice.']);

        $html = $this->get('/services')->assertOk()->getContent();

        /* The site-wide title pattern from Settings is applied here — the page's own title still
           leads, which is the part that matters to a reader scanning search results. */
        $this->assertStringContainsString('<title inertia>What we do | Seniors Property Advisors</title>', $html);
        $this->assertStringContainsString('property="og:title" content="What we do | Seniors Property Advisors"', $html);
        $this->assertStringContainsString('name="description" content="Independent advice."', $html);
        $this->assertStringContainsString('rel="canonical"', $html);
    }

    public function test_an_article_carries_its_own_title_and_picture_in_the_html(): void
    {
        $this->post('/cms/blog', [
            'title' => 'Planning a downsize',
            'summary' => 'Where to start.',
            'body' => '<p>Words.</p>',
            'featured_image' => '/media/2026/08/hero.jpg',
        ])->assertRedirect();

        BlogPost::sole()->update(['status' => 'published', 'published_at' => now()]);

        $html = $this->get('/blog/planning-a-downsize')->assertOk()->getContent();

        /* The bug in one line: every page used to report the site title here. */
        $this->assertStringContainsString('<title inertia>Planning a downsize | Seniors Property Advisors</title>', $html);
        $this->assertStringContainsString('property="og:type" content="article"', $html);
        $this->assertStringContainsString('/media/2026/08/hero.jpg', $html);
        $this->assertStringContainsString('application/ld+json', $html);
        $this->assertStringContainsString('"@type":"Article"', $html);
    }

    public function test_an_article_hidden_from_search_is_not_marked_up_as_an_article(): void
    {
        $this->post('/cms/blog', [
            'title' => 'Quietly published',
            'body' => '<p>Words.</p>',
            'seo' => ['noindex' => true],
        ])->assertRedirect();

        BlogPost::sole()->update(['status' => 'published', 'published_at' => now()]);

        $html = $this->get('/blog/quietly-published')->assertOk()->getContent();

        $this->assertStringContainsString('name="robots" content="noindex, follow"', $html);
        $this->assertStringNotContainsString('application/ld+json', $html);
    }

    /**
     * The three renders that used to emit no head at all. A preview address is behind
     * permit:content.manage, but a leaked one is the classic way an unfinished page reaches
     * search — and the sign-in form is not content, so it is told not to follow anything either.
     */
    public function test_nothing_internal_invites_a_crawler(): void
    {
        $page = $this->page('services');

        /* The suite signs in for every test; /login is for whoever is not. */
        $signedIn = auth()->user();
        auth()->logout();

        $this->assertStringContainsString(
            'name="robots" content="noindex, nofollow"',
            $this->get('/login')->assertOk()->getContent(),
        );

        $this->actingAs($signedIn);

        (new PageContentStore)->saveDraft('services', [
            ['id' => 'a', 'type' => 'hero', 'active' => true, 'data' => ['heading' => 'Draft']],
        ], 'Tester');

        $this->assertStringContainsString(
            'name="robots" content="noindex, follow"',
            $this->get("/cms/pages/{$page->cms_id}/preview")->assertOk()->getContent(),
        );
    }

    /** Forced, so a page the editor has chosen to index still previews un-indexably. */
    public function test_a_preview_is_hidden_even_when_the_page_itself_is_not(): void
    {
        $page = $this->page('services');

        $this->details($page, ['title' => 'What we do', 'noindex' => false]);

        $this->assertStringContainsString(
            'name="robots" content="noindex, follow"',
            $this->get("/cms/pages/{$page->cms_id}/preview")->assertOk()->getContent(),
        );

        $this->assertStringNotContainsString(
            'name="robots"',
            $this->get('/services')->assertOk()->getContent(),
        );
    }

    public function test_an_article_preview_is_hidden_and_describes_nothing(): void
    {
        $this->post('/cms/blog', [
            'title' => 'Planning a downsize',
            'body' => '<p>Words.</p>',
        ])->assertRedirect();

        $post = BlogPost::sole();
        $post->update(['status' => 'published', 'published_at' => now()]);

        $html = $this->get("/cms/blog/{$post->id}/preview")->assertOk()->getContent();

        $this->assertStringContainsString('name="robots" content="noindex, follow"', $html);
        $this->assertStringNotContainsString('application/ld+json', $html);
    }

    public function test_a_published_article_carries_the_dates_its_structured_data_needs(): void
    {
        $this->post('/cms/blog', [
            'title' => 'Planning a downsize',
            'body' => '<p>Words.</p>',
            'author_name' => 'Helen Marsh',
            'published_at' => '2026-08-01',
        ])->assertRedirect();

        $post = BlogPost::sole();
        $post->update(['status' => 'published']);

        $article = $post->fresh()->toArticle();

        /* Machine-readable, not the "1 August 2026" a reader sees. */
        $this->assertStringStartsWith('2026-08-01T', $article['publishedAt']);
        $this->assertNotNull($article['updatedAt']);
        $this->assertSame('Helen Marsh', $article['author']);
    }
}
