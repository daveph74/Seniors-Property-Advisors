<?php

namespace Tests\Feature;

use App\Content\PageContentStore;
use App\Content\StarterLayouts;
use App\Models\Page;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CreatePageTest extends TestCase
{
    private function shape(array $sections): array
    {
        return array_map(fn ($block) => [
            'id' => $block['id'],
            'type' => $block['type'],
            'label' => $block['label'],
            'keys' => array_keys($block['data']),
            'children' => $this->shape($block['children'] ?? []),
        ], $sections);
    }

    private function create(array $payload = [])
    {
        return $this->post('/cms/pages', array_merge(['title' => 'Preparing to Move'], $payload));
    }

    public function test_creating_a_page_opens_its_builder(): void
    {
        $this->create(['layout' => 'standard'])
            ->assertRedirect('/cms/pages/2/edit')
            ->assertSessionHasNoErrors();

        $page = Page::where('slug', 'preparing-to-move')->first();

        $this->assertNotNull($page);
        $this->assertSame(2, $page->cms_id);
        $this->assertSame('/preparing-to-move', $page->url);
        $this->assertSame('draft', $page->status);
        $this->assertSame([], $page->published);
        $this->assertNotEmpty($page->draft);
    }

    public function test_a_blank_page_starts_with_no_sections(): void
    {
        $this->create(['layout' => 'blank'])->assertRedirect();

        $this->assertSame([], Page::where('slug', 'preparing-to-move')->value('draft'));
    }

    public function test_the_list_counts_sections_a_draft_page_has_not_published_yet(): void
    {
        $this->create(['title' => 'About Us', 'layout' => 'landing'])->assertRedirect();

        $row = collect((new PageContentStore)->all())->firstWhere('url', '/about-us');

        $this->assertSame(4, $row['sectionCount']);
        $this->assertSame('draft', $row['status']);
    }

    public function test_creating_under_a_parent_nests_the_url(): void
    {
        $this->create(['title' => 'About Us'])->assertRedirect();
        $this->create(['title' => 'Selling Guide', 'parent' => 'about-us'])->assertRedirect();

        $child = Page::where('slug', 'about-us/selling-guide')->first();

        $this->assertNotNull($child);
        $this->assertSame('/about-us/selling-guide', $child->url);

        $row = collect((new PageContentStore)->all())->firstWhere('url', '/about-us/selling-guide');

        $this->assertSame(1, $row['depth']);
        $this->assertSame(0, collect((new PageContentStore)->all())->firstWhere('url', '/about-us')['depth']);
    }

    public function test_home_cannot_be_used_as_a_parent(): void
    {
        $this->create(['title' => 'Orphan', 'parent' => 'home'])->assertRedirect();

        $this->assertTrue(Page::where('slug', 'orphan')->exists());
    }

    public function test_a_repeated_name_gets_a_numbered_slug(): void
    {
        $this->create(['title' => 'About Us'])->assertRedirect();
        $this->create(['title' => 'About Us'])->assertRedirect();

        $this->assertTrue(Page::where('slug', 'about-us')->exists());
        $this->assertTrue(Page::where('slug', 'about-us-2')->exists());
    }

    public function test_reserved_names_are_rejected(): void
    {
        foreach (['CMS', 'Build', 'Storage', 'Up', 'Home', 'api'] as $name) {
            $this->create(['title' => $name])->assertSessionHasErrors('title');
        }

        $this->assertSame(1, Page::count());
    }

    public function test_a_name_with_no_letters_or_numbers_is_rejected(): void
    {
        $this->create(['title' => '!!!'])->assertSessionHasErrors('title');
        $this->create(['title' => ''])->assertSessionHasErrors('title');

        $this->assertSame(1, Page::count());
    }

    public function test_an_unknown_parent_or_layout_is_rejected(): void
    {
        $this->create(['parent' => 'nope'])->assertSessionHasErrors('parent');
        $this->create(['layout' => 'wat'])->assertSessionHasErrors('layout');

        $this->assertSame(1, Page::count());
    }

    public function test_markup_is_stripped_from_the_name(): void
    {
        $this->create(['title' => '<b>Bold Page</b>'])->assertRedirect();

        $this->assertSame('Bold Page', Page::where('cms_id', 2)->value('title'));
    }

    public function test_every_starter_layout_saves_cleanly(): void
    {
        foreach (array_keys(StarterLayouts::OPTIONS) as $i => $key) {
            $this->create(['title' => "Layout {$key}", 'layout' => $key])->assertRedirect();

            $slug = 'layout-'.$key;
            $tree = (new PageContentStore)->editable($slug);

            $this->post('/cms/pages/'.($i + 2).'/draft', ['sections' => $tree])
                ->assertRedirect()
                ->assertSessionHasNoErrors();

            $saved = (new PageContentStore)->editable($slug);

            $this->assertSame($this->shape($tree), $this->shape($saved));
        }
    }

    public function test_a_new_page_is_not_public_until_it_is_published(): void
    {
        $this->create(['title' => 'About Us', 'layout' => 'standard'])->assertRedirect();

        $this->get('/about-us')->assertNotFound();

        $this->post('/cms/pages/2/publish-now')->assertRedirect();

        $this->get('/about-us')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->component('AgentFinder')->where('title', 'About Us'));

        $this->post('/cms/pages/2/archive')->assertRedirect();
        $this->get('/about-us')->assertNotFound();
    }

    public function test_a_nested_page_renders_at_its_nested_url(): void
    {
        $this->create(['title' => 'About Us'])->assertRedirect();
        $this->create(['title' => 'Selling Guide', 'parent' => 'about-us', 'layout' => 'standard'])->assertRedirect();

        $this->post('/cms/pages/3/publish-now')->assertRedirect();

        $this->get('/about-us/selling-guide')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('title', 'Selling Guide'));
    }

    public function test_a_nested_page_clears_its_cache_when_unpublished(): void
    {
        config(['app.debug' => false]);

        $this->create(['title' => 'About Us'])->assertRedirect();
        $this->create(['title' => 'Selling Guide', 'parent' => 'about-us', 'layout' => 'standard'])->assertRedirect();
        $this->post('/cms/pages/3/publish-now')->assertRedirect();

        $this->get('/about-us/selling-guide')->assertOk();

        $this->post('/cms/pages/3/unpublish')->assertRedirect();

        $this->get('/about-us/selling-guide')->assertNotFound();
    }

    public function test_the_public_route_does_not_swallow_the_cms(): void
    {
        $this->get('/cms/pages')->assertOk();
        $this->get('/cms/nope')->assertNotFound();
        $this->get('/')->assertOk();
    }

    public function test_the_home_slug_redirects_to_the_root(): void
    {
        $this->get('/home')->assertRedirect('/');
    }

    public function test_an_unknown_path_is_a_404(): void
    {
        $this->get('/nothing-here')->assertNotFound();
    }

    public function test_the_builder_receives_the_starter_layouts(): void
    {
        $this->get('/cms/pages')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->has('layouts', 4)
                ->where('layouts.0.key', 'blank'));
    }
}
