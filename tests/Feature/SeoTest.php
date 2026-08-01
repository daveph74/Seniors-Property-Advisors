<?php

namespace Tests\Feature;

use App\Content\Seo;
use App\Models\BlogPost;
use App\Models\Media;
use App\Models\Page;
use Tests\TestCase;

class SeoTest extends TestCase
{
    private function media(array $overrides = []): Media
    {
        return Media::create(array_merge([
            'key' => '2026/07/card.png',
            'name' => 'card.png',
            'mime' => 'image/png',
            'size' => 2048,
            'width' => 1200,
            'height' => 630,
            'disk' => 's3',
        ], $overrides));
    }

    private function article(array $overrides = []): BlogPost
    {
        return BlogPost::create(array_merge([
            'slug' => 'an-article',
            'title' => 'An article',
            'summary' => 'A short summary.',
            'body' => '<p>Words.</p>',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ], $overrides));
    }

    /**
     * The bug this all hangs on: Open Graph needs an absolute URL, and content stores
     * `/media/…`, which is right for an <img> and useless to a crawler.
     */
    public function test_a_media_path_becomes_an_absolute_address(): void
    {
        $this->assertSame(url('/media/2026/07/card.png'), Seo::absolute('/media/2026/07/card.png'));
        $this->assertStringStartsWith('http', Seo::absolute('/media/2026/07/card.png'));
    }

    public function test_an_address_that_is_already_absolute_is_left_alone(): void
    {
        $this->assertSame('https://cdn.example.com/a.png', Seo::absolute('https://cdn.example.com/a.png'));
        $this->assertSame('//cdn.example.com/a.png', Seo::absolute('//cdn.example.com/a.png'));
    }

    public function test_no_image_gives_nothing_rather_than_a_broken_address(): void
    {
        $this->assertNull(Seo::absolute(null));
        $this->assertNull(Seo::absolute(''));
        $this->assertNull(Seo::absolute('   '));
    }

    public function test_the_size_comes_from_the_media_library(): void
    {
        $this->media();

        $sharing = Seo::forSharing(['image' => '/media/2026/07/card.png'], null, url('/'));

        $this->assertSame(1200, $sharing['imageWidth']);
        $this->assertSame(630, $sharing['imageHeight']);
    }

    /**
     * A wrong width is worse than none, so anything we did not store is left unstated.
     */
    public function test_an_unknown_image_carries_no_size(): void
    {
        $external = Seo::forSharing(['image' => 'https://cdn.example.com/a.png'], null, url('/'));
        $unsized = Seo::forSharing(['image' => '/media/2026/07/nosize.png'], null, url('/'));

        $this->media(['key' => '2026/07/nosize.png', 'width' => null, 'height' => null]);

        $this->assertArrayNotHasKey('imageWidth', $external);
        $this->assertArrayNotHasKey('imageHeight', $external);
        $this->assertArrayNotHasKey('imageWidth', $unsized);
    }

    public function test_a_page_shares_an_absolute_image_with_its_size(): void
    {
        $this->media();

        Page::where('slug', 'home')->update(['seo' => ['image' => '/media/2026/07/card.png']]);

        $this->get('/')->assertOk()->assertInertia(function ($props) {
            $seo = $props->toArray()['props']['seo'];

            $this->assertSame(url('/media/2026/07/card.png'), $seo['image']);
            $this->assertSame(1200, $seo['imageWidth']);
            $this->assertSame(630, $seo['imageHeight']);
            $this->assertStringStartsWith('http', $seo['url']);
        });
    }

    public function test_an_article_falls_back_to_its_featured_image(): void
    {
        $this->media();

        $post = $this->article(['featured_image' => '/media/2026/07/card.png']);

        $this->get("/blog/{$post->slug}")->assertOk()->assertInertia(function ($props) use ($post) {
            $seo = $props->toArray()['props']['seo'];

            $this->assertSame(url('/media/2026/07/card.png'), $seo['image']);
            $this->assertSame(1200, $seo['imageWidth']);
            $this->assertSame(url("/blog/{$post->slug}"), $seo['url']);
        });
    }

    public function test_an_article_prefers_its_own_sharing_image(): void
    {
        $this->media();
        $this->media(['key' => '2026/07/social.png', 'width' => 1200, 'height' => 630]);

        $post = $this->article([
            'featured_image' => '/media/2026/07/card.png',
            'seo' => ['image' => '/media/2026/07/social.png'],
        ]);

        $this->get("/blog/{$post->slug}")->assertOk()->assertInertia(function ($props) {
            $this->assertSame(
                url('/media/2026/07/social.png'),
                $props->toArray()['props']['seo']['image'],
            );
        });
    }

    public function test_an_article_carries_an_address_for_the_canonical_tag(): void
    {
        $post = $this->article();

        $this->get("/blog/{$post->slug}")->assertOk()->assertInertia(function ($props) use ($post) {
            $seo = $props->toArray()['props']['seo'];

            $this->assertSame(url("/blog/{$post->slug}"), $seo['url']);
            $this->assertSame($post->title, $seo['title']);
        });
    }

    public function test_an_svg_sharing_image_is_refused_on_a_page(): void
    {
        $page = Page::where('slug', 'home')->firstOrFail();

        $this->patch("/cms/pages/{$page->cms_id}/details", [
            'title' => $page->title,
            'seo' => ['image' => '/media/2026/07/logo.svg'],
        ])->assertSessionHasErrors('seo.image');

        $this->assertArrayNotHasKey('image', $page->refresh()->seo ?? []);
    }

    public function test_an_svg_sharing_image_is_refused_on_an_article(): void
    {
        $this->post('/cms/blog', [
            'title' => 'A new article',
            'body' => '<p>Words.</p>',
            'seo' => ['image' => '/media/2026/07/logo.svg'],
        ])->assertSessionHasErrors('seo.image');

        $this->assertSame(0, BlogPost::count());
    }
}
