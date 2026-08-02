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
