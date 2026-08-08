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

    /**
     * The site's pictures live in the media library now, but the settings screen still accepts any
     * path beginning with a slash, so somebody can point the sharing image at a file shipped in
     * public/. Without the width and height a crawler draws the small card, which is the one thing
     * setting a default was meant to stop — so that path has to be measured off disk.
     */
    public function test_a_picture_shipped_with_the_application_is_measured_from_the_file(): void
    {
        $path = public_path('images/measured-in-a-test.png');

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        imagepng(imagecreatetruecolor(64, 48), $path);

        try {
            $sharing = Seo::forSharing(['image' => '/images/measured-in-a-test.png'], null, url('/'));

            $this->assertSame(64, $sharing['imageWidth']);
            $this->assertSame(48, $sharing['imageHeight']);
        } finally {
            @unlink($path);
        }
    }

    /**
     * The path arrives from the settings screen, so it is somebody's input. getimagesize() opens
     * a file, and a path that walks out of public/ would turn the sharing-image field into a way
     * to ask whether an arbitrary file exists.
     */
    public function test_a_path_outside_the_public_folder_is_not_opened(): void
    {
        foreach (['/../.env', '/images/../../.env', '//evil.example.com/a.png', 'index.php'] as $path) {
            $this->assertArrayNotHasKey(
                'imageWidth',
                Seo::forSharing(['image' => $path], null, url('/')),
                "{$path} should not have been read",
            );
        }
    }

    public function test_a_missing_file_carries_no_size(): void
    {
        $sharing = Seo::forSharing(['image' => '/images/not-here.png'], null, url('/'));

        $this->assertArrayNotHasKey('imageWidth', $sharing);
        $this->assertSame(url('/images/not-here.png'), $sharing['image']);
    }

    /**
     * Every page shares something, so no link goes out with a blank preview. The default lives in
     * the media library like everything else, so the row is what carries the size — MediaSeeder
     * writes it for real; here the test writes its own rather than seeding the whole library into
     * every case.
     */
    public function test_a_page_with_no_picture_of_its_own_falls_back_to_the_site_default(): void
    {
        $this->media(['key' => '2026/08/share-card.jpg', 'name' => 'share-card.jpg', 'width' => 1200, 'height' => 630]);

        $this->get('/how-it-works')->assertOk()->assertInertia(function ($props) {
            $seo = $props->toArray()['props']['seo'];

            $this->assertSame(url('/media/2026/08/share-card.jpg'), $seo['image']);
            $this->assertSame(1200, $seo['imageWidth']);
            $this->assertSame(630, $seo['imageHeight']);
        });

        $this->get('/how-it-works')->assertSee('name="twitter:card" content="summary_large_image"', false);
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
