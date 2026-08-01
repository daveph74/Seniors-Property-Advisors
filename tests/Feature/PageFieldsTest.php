<?php

namespace Tests\Feature;

use App\Content\PageContentStore;
use App\Models\Page;
use App\Models\PageRedirect;
use App\Models\Setting;
use Tests\TestCase;

class PageFieldsTest extends TestCase
{
    private function store(): PageContentStore
    {
        return new PageContentStore;
    }

    private function page(string $slug, string $status = 'published'): Page
    {
        return Page::create([
            'cms_id' => Page::max('cms_id') + 1,
            'slug' => $slug,
            'url' => '/'.$slug,
            'title' => ucfirst($slug),
            'status' => $status,
            'seo' => [],
            'draft' => null,
            'published' => [['id' => 'h', 'type' => 'heading', 'label' => 'Heading', 'active' => true, 'data' => ['heading' => 'Hi']]],
        ]);
    }

    private function details(Page $page, array $body): void
    {
        $this->patch("/cms/pages/{$page->cms_id}/details", array_merge([
            'title' => $page->title,
        ], $body))->assertRedirect();
    }

    public function test_it_saves_a_menu_label_and_exposes_it(): void
    {
        $page = $this->page('services');

        $this->details($page, ['navLabel' => 'Services']);

        $this->assertSame('Services', $page->refresh()->nav_label);
        $this->assertSame('Services', $this->store()->document('services')['navLabel']);
    }

    public function test_saving_only_the_title_leaves_the_menu_label_alone(): void
    {
        $page = $this->page('services');
        $page->forceFill(['nav_label' => 'Services'])->save();

        $this->details($page, ['title' => 'Our Services']);

        $this->assertSame('Services', $page->refresh()->nav_label);
    }

    public function test_the_site_menu_uses_the_menu_label(): void
    {
        $page = $this->page('services');
        $page->forceFill(['nav_label' => 'What we do'])->save();

        Setting::updateOrCreate(
            ['key' => 'globals'],
            ['value' => ['nav' => ['links' => [['href' => '/services', 'label' => 'Services']]]]],
        );

        $links = $this->store()->globals()['nav']['links'];

        $this->assertSame('What we do', $links[0]['label']);
    }

    public function test_the_blog_is_reachable_from_the_menu_and_the_footer(): void
    {
        $globals = $this->store()->globals();

        $this->assertContains('/blog', array_column($globals['nav']['links'], 'href'));

        $resources = collect($globals['footer']['columns'])->firstWhere('heading', 'Resources');

        $this->assertContains('/blog', array_column($resources['links'], 'href'));
    }

    public function test_the_blog_link_is_not_added_twice(): void
    {
        $before = $this->store()->globals();

        $this->artisan('migrate', ['--force' => true])->assertSuccessful();

        $links = array_column($this->store()->globals()['nav']['links'], 'href');

        $this->assertSame(
            count(array_column($before['nav']['links'], 'href')),
            count($links),
        );
        $this->assertSame(1, count(array_keys($links, '/blog', true)));
    }

    public function test_the_menu_label_follows_the_blog_page(): void
    {
        $blog = Page::create([
            'cms_id' => Page::max('cms_id') + 1,
            'slug' => 'blog',
            'url' => '/blog',
            'title' => 'Blog',
            'nav_label' => 'Advice',
            'status' => 'published',
            'seo' => [],
            'published' => [],
        ]);

        $link = collect($this->store()->globals()['nav']['links'])->firstWhere('href', '/blog');

        $this->assertSame('Advice', $link['label']);

        $blog->forceFill(['nav_label' => 'Articles'])->save();

        $link = collect($this->store()->globals()['nav']['links'])->firstWhere('href', '/blog');

        $this->assertSame('Articles', $link['label']);
    }

    public function test_renaming_a_draft_leaves_no_redirect(): void
    {
        $page = $this->page('old-name', 'draft');

        $this->details($page, ['slug' => 'new-name']);

        $this->assertSame('new-name', $page->refresh()->slug);
        $this->assertSame(0, PageRedirect::count());
    }

    public function test_renaming_a_published_page_redirects_the_old_address(): void
    {
        $page = $this->page('old-name');

        $this->details($page, ['slug' => 'new-name']);

        $page->refresh();

        $this->assertSame('new-name', $page->slug);
        $this->assertSame('/new-name', $page->url);

        $this->get('/old-name')->assertRedirect('/new-name')->assertStatus(301);
        $this->get('/new-name')->assertOk();
    }

    public function test_moving_twice_collapses_the_chain(): void
    {
        $page = $this->page('a');

        $this->details($page, ['slug' => 'b']);
        $this->details($page->refresh(), ['slug' => 'c']);

        $this->assertSame(['/a' => '/c', '/b' => '/c'], PageRedirect::pluck('to_url', 'from_url')->all());
        $this->get('/a')->assertRedirect('/c');
    }

    public function test_moving_back_removes_the_redirect_that_would_shadow_it(): void
    {
        $page = $this->page('a');

        $this->details($page, ['slug' => 'b']);
        $this->details($page->refresh(), ['slug' => 'a']);

        $this->assertSame(0, PageRedirect::where('from_url', '/a')->count());
        $this->get('/a')->assertOk();
    }

    public function test_a_taken_address_is_not_reused(): void
    {
        $this->page('taken');
        $page = $this->page('mine');

        $this->details($page, ['slug' => 'taken']);

        $this->assertNotSame('taken', $page->refresh()->slug);
        $this->assertSame(2, Page::where('slug', 'like', 'taken%')->count());
    }

    public function test_a_bad_address_is_rejected(): void
    {
        $page = $this->page('fine');

        $this->patch("/cms/pages/{$page->cms_id}/details", [
            'title' => 'Fine',
            'slug' => 'Not Valid!',
        ])->assertSessionHasErrors('slug');

        $this->assertSame('fine', $page->refresh()->slug);
    }

    public function test_the_home_page_cannot_be_moved(): void
    {
        $home = Page::where('slug', 'home')->firstOrFail();

        $this->details($home, ['slug' => 'somewhere-else']);

        $this->assertSame('home', $home->refresh()->slug);
    }

    public function test_the_old_address_stops_serving_the_page_after_a_rename(): void
    {
        $page = $this->page('before');

        $this->get('/before')->assertOk();

        $this->details($page, ['slug' => 'after']);

        $this->get('/after')->assertOk();
        $this->get('/before')->assertRedirect('/after');
    }

    public function test_the_sharing_image_is_saved_and_rendered(): void
    {
        $page = $this->page('shared');

        $this->details($page, ['seo' => ['image' => '/media/2026/07/card.png']]);

        $this->assertSame('/media/2026/07/card.png', $page->refresh()->seo['image']);

        $this->get('/shared')->assertOk()->assertInertia(function ($props) {
            $seo = $props->toArray()['props']['seo'];

            $this->assertSame('/media/2026/07/card.png', $seo['image']);
            $this->assertStringEndsWith('/shared', $seo['url']);
        });
    }

    public function test_the_scaffold_command_creates_the_agreed_pages_as_drafts(): void
    {
        $this->artisan('pages:scaffold')->assertSuccessful();

        foreach (['about-us', 'our-services', 'how-it-works', 'resources', 'faqs', 'contact', 'privacy-policy', 'terms-and-conditions'] as $slug) {
            $page = Page::where('slug', $slug)->first();

            $this->assertNotNull($page, "{$slug} was not created");
            $this->assertSame('draft', $page->status);
            $this->assertNotNull($page->nav_label);
        }
    }

    public function test_the_scaffold_command_is_safe_to_run_twice(): void
    {
        $this->artisan('pages:scaffold')->assertSuccessful();
        $before = Page::count();

        $this->artisan('pages:scaffold')->assertSuccessful();

        $this->assertSame($before, Page::count());
    }
}
