<?php

namespace Tests\Feature;

use App\Content\PageContentStore;
use App\Models\BlogPost;
use App\Models\Page;
use Tests\TestCase;

/**
 * A sitemap is a promise about which addresses are worth fetching. Listing one that answers 404,
 * or one the same page then tells the crawler to forget, spends the site's crawl budget arguing
 * with itself — so these are all about what stays out.
 */
class SitemapTest extends TestCase
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

    public function test_it_serves_xml(): void
    {
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<urlset', false)
            ->assertSee('http://www.sitemaps.org/schemas/sitemap/0.9', false);
    }

    public function test_it_lists_the_published_pages_at_their_real_addresses(): void
    {
        $response = $this->get('/sitemap.xml');

        foreach (['/how-it-works', '/why-agent-finder', '/compare-agents', '/for-families', '/faqs', '/blog'] as $path) {
            $response->assertSee('<loc>'.url($path).'</loc>', false);
        }

        /* The home page lives at the site root, never at /home — which 301s. */
        $response->assertSee('<loc>'.url('/').'</loc>', false);
        $response->assertDontSee('<loc>'.url('/home').'</loc>', false);
    }

    /**
     * `lastmod` is a promise that the reader's copy changed, and a page has a draft — so its
     * `updated_at` moves the moment somebody saves work nobody can see. Reporting that asks every
     * crawler to re-fetch a page that did not move, which is the one thing this field exists to
     * avoid, so a page's answer comes from its last publish instead.
     */
    public function test_saving_a_draft_does_not_tell_crawlers_the_live_page_changed(): void
    {
        $store = new PageContentStore;

        /* Published through the CMS first, because that is what stamps `published_at`. A seeded
           page has never been published by anybody, so it has no honest answer — asserted below. */
        $store->publish('faqs', 'Tester');

        $before = $this->lastmodFor(url('/faqs'));
        $this->assertNotNull($before);

        $this->travel(2)->days();
        $store->saveDraft('faqs', [['id' => 'a', 'type' => 'hero', 'active' => true, 'data' => []]], 'Tester');

        $this->assertSame($before, $this->lastmodFor(url('/faqs')), 'a draft save moved lastmod');

        $store->publish('faqs', 'Tester');

        $this->assertNotSame($before, $this->lastmodFor(url('/faqs')), 'publishing did not move lastmod');
    }

    /**
     * A page nobody has published through the CMS has no publish date, so "when did this last
     * change" is genuinely unknown. It is left out rather than filled in from `updated_at` —
     * which was the first attempt, and put the draft-save time back for exactly the pages the
     * rule above protects.
     */
    public function test_a_page_with_no_publish_date_is_listed_without_a_lastmod(): void
    {
        Page::where('slug', 'faqs')->update(['published_at' => null]);

        $this->get('/sitemap.xml')->assertOk()->assertSee('<loc>'.url('/faqs').'</loc>', false);

        $this->assertNull($this->lastmodFor(url('/faqs')));
    }

    private function lastmodFor(string $loc): ?string
    {
        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

        return preg_match('#<loc>'.preg_quote($loc, '#').'</loc>\s*<lastmod>([^<]+)</lastmod>#', $xml, $m) === 1
            ? $m[1]
            : null;
    }

    /**
     * The blog's load-more endpoint answers with article content and no page around it, which is a
     * duplicate of the listing to anything that indexes it.
     */
    public function test_robots_keeps_crawlers_out_of_the_json_endpoint(): void
    {
        $this->assertStringContainsString('Disallow: /blog/articles', $this->get('/robots.txt')->getContent());
    }

    public function test_a_page_hidden_from_search_is_not_advertised(): void
    {
        Page::where('slug', 'faqs')->update(['seo' => ['noindex' => true]]);

        $this->get('/sitemap.xml')->assertDontSee('<loc>'.url('/faqs').'</loc>', false);
    }

    /**
     * The archived hero preview is the case this was written for: it was noindexed *and* is now
     * off the website, and either reason alone should be enough to keep it out.
     */
    public function test_a_draft_or_archived_page_is_not_advertised(): void
    {
        Page::where('slug', 'compare-agents')->update(['status' => 'draft']);

        $this->get('/sitemap.xml')
            ->assertDontSee('<loc>'.url('/compare-agents').'</loc>', false)
            ->assertDontSee('<loc>'.url('/hero-preview').'</loc>', false);
    }

    public function test_it_lists_a_published_article(): void
    {
        $post = $this->article();

        $this->get('/sitemap.xml')->assertSee('<loc>'.url("/blog/{$post->slug}").'</loc>', false);
    }

    public function test_an_unpublished_or_hidden_article_is_not_advertised(): void
    {
        $this->article(['slug' => 'a-draft', 'status' => 'draft']);
        $this->article(['slug' => 'a-hidden-one', 'seo' => ['noindex' => true]]);
        $this->article(['slug' => 'a-deleted-one'])->delete();

        $this->get('/sitemap.xml')
            ->assertDontSee('a-draft', false)
            ->assertDontSee('a-hidden-one', false)
            ->assertDontSee('a-deleted-one', false);
    }

    /**
     * robots.txt is served by the application rather than sat in public/, so the sitemap address
     * is built from whatever host the site is answering on. The static file it replaced named no
     * sitemap at all and would have had to name one domain for every environment.
     */
    public function test_robots_names_the_sitemap_on_this_host(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Sitemap: '.url('/sitemap.xml'))
            ->assertSee('Disallow: /cms/');
    }
}
