<?php

namespace Tests\Feature;

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
