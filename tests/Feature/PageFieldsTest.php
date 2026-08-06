<?php

namespace Tests\Feature;

use App\Content\PageContentStore;
use App\Models\Faq;
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

    private function resourcesMenu(): array
    {
        return collect($this->store()->globals()['nav']['links'])->firstWhere('label', 'Resources') ?? [];
    }

    public function test_the_blog_sits_under_resources_and_in_the_footer(): void
    {
        $globals = $this->store()->globals();
        $resources = $this->resourcesMenu();

        $this->assertContains('/blog', array_column($resources['children'], 'href'));

        /* The parent is a trigger, not a link — its own page is still a draft. */
        $this->assertNull($resources['href']);

        /* Nothing left at the top level, or the menu would list Blog twice. */
        $this->assertNotContains('/blog', array_column($globals['nav']['links'], 'href'));

        $footer = collect($globals['footer']['columns'])->firstWhere('heading', 'Resources');

        $this->assertContains('/blog', array_column($footer['links'], 'href'));
    }

    public function test_the_faq_page_is_reachable_from_the_menu_and_the_footer(): void
    {
        $children = array_column($this->resourcesMenu()['children'], 'href');

        $this->assertContains('/faqs', $children);

        /* Above Blog: someone opening Resources with a question wants an answer, not an article. */
        $this->assertLessThan(array_search('/blog', $children, true), array_search('/faqs', $children, true));

        $footer = collect($this->store()->globals()['footer']['columns'])->firstWhere('heading', 'Resources');

        /* Repointed, not added — the label was already there against a placeholder. */
        $this->assertContains('/faqs', array_column($footer['links'], 'href'));
        $this->assertSame(1, count(array_keys(array_column($footer['links'], 'label'), 'FAQs', true)));
    }

    public function test_a_published_faq_section_carries_the_questions_to_the_reader(): void
    {
        Faq::create([
            'question' => 'What does an advisor cost?',
            'answer' => 'Nothing upfront.',
            'sort_order' => 1,
            'active' => true,
        ]);

        $page = $this->page('questions');
        $page->forceFill([
            'status' => 'published',
            'published' => [['id' => 'f1', 'type' => 'faq-list', 'active' => true, 'data' => []]],
        ])->save();

        $this->get('/questions')->assertOk()->assertInertia(function ($props) {
            $data = $props->toArray()['props'];

            $this->assertSame('faq-list', $data['sections'][0]['type']);
            $this->assertContains(
                'What does an advisor cost?',
                array_column($data['library']['faqs'], 'question'),
            );
        });
    }

    public function test_the_blog_link_is_not_added_twice(): void
    {
        $before = count($this->resourcesMenu()['children']);

        $this->artisan('migrate', ['--force' => true])->assertSuccessful();

        $this->assertCount($before, $this->resourcesMenu()['children']);
        $this->assertSame(
            1,
            count(array_keys(array_column($this->resourcesMenu()['children'], 'href'), '/blog', true)),
        );
    }

    /**
     * Relabelling used to walk only the top level. Blog is nested now, so a page rename that
     * stopped reaching it would be a quiet inconsistency.
     */
    public function test_the_menu_label_follows_the_blog_page_even_when_nested(): void
    {
        /* The blog page ships in the seed now, so this relabels it rather than creating a
           second one — the slug is unique and a duplicate would fail on insert. */
        $blog = Page::where('slug', 'blog')->firstOrFail();

        $blog->forceFill(['nav_label' => 'Advice'])->save();

        $child = collect($this->resourcesMenu()['children'])->firstWhere('href', '/blog');

        $this->assertSame('Advice', $child['label']);

        $blog->forceFill(['nav_label' => 'Articles'])->save();

        $child = collect($this->resourcesMenu()['children'])->firstWhere('href', '/blog');

        $this->assertSame('Articles', $child['label']);
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

            /* Stored relative for <img>, shared absolute because Open Graph requires it. */
            $this->assertSame(url('/media/2026/07/card.png'), $seo['image']);
            $this->assertStringEndsWith('/shared', $seo['url']);
        });
    }

    public function test_the_scaffold_command_creates_the_agreed_pages_as_drafts(): void
    {
        $this->artisan('pages:scaffold')->assertSuccessful();

        /* Only the pages with no content of their own. how-it-works, blog and faqs ship from
           the seed already published, so scaffolding must leave them alone rather than replace
           them with empty drafts. */
        foreach (['about-us', 'our-services', 'resources', 'contact', 'privacy-policy', 'terms-and-conditions'] as $slug) {
            $page = Page::where('slug', $slug)->first();

            $this->assertNotNull($page, "{$slug} was not created");
            $this->assertSame('draft', $page->status);
            $this->assertNotNull($page->nav_label);
        }

        foreach (['how-it-works', 'blog', 'faqs'] as $slug) {
            $seeded = Page::where('slug', $slug)->firstOrFail();

            $this->assertSame('published', $seeded->status, "{$slug} was overwritten by the scaffold");
            $this->assertNotEmpty($seeded->published);
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
