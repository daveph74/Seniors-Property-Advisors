<?php

namespace Tests\Feature;

use App\Content\PageContentStore;
use App\Models\Page;
use App\Models\PageRevision;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PageActionsTest extends TestCase
{
    private function pageStatus(string $slug = 'home'): string
    {
        return Page::where('slug', $slug)->value('status');
    }

    public function test_unpublishing_takes_the_page_off_the_public_site(): void
    {
        $this->get('/')->assertOk();

        $this->post('/cms/pages/1/unpublish')->assertRedirect();

        $this->assertSame('draft', $this->pageStatus());
        $this->get('/')->assertNotFound();
    }

    public function test_unpublishing_keeps_the_published_content_so_it_can_go_back_up(): void
    {
        $before = (new PageContentStore)->document('home')['published'];

        $this->post('/cms/pages/1/unpublish')->assertRedirect();
        $this->assertSame($before, (new PageContentStore)->document('home')['published']);

        $this->post('/cms/pages/1/publish-now')->assertRedirect();

        $this->assertSame('published', $this->pageStatus());
        $this->get('/')->assertOk();
    }

    public function test_unpublishing_clears_the_cached_public_page(): void
    {
        config(['app.debug' => false]);

        $this->get('/')->assertOk();
        $this->post('/cms/pages/1/unpublish')->assertRedirect();

        $this->get('/')->assertNotFound();
    }

    public function test_archiving_hides_the_page_and_can_be_undone(): void
    {
        $this->post('/cms/pages/1/archive')->assertRedirect();

        $this->assertSame('archived', $this->pageStatus());
        $this->get('/')->assertNotFound();

        $this->post('/cms/pages/1/unarchive')->assertRedirect();

        $this->assertSame('published', $this->pageStatus());
        $this->get('/')->assertOk();
    }

    public function test_unarchiving_a_page_that_never_published_returns_it_to_draft(): void
    {
        Page::where('slug', 'home')->update(['published' => []]);

        $this->post('/cms/pages/1/archive')->assertRedirect();
        $this->post('/cms/pages/1/unarchive')->assertRedirect();

        $this->assertSame('draft', $this->pageStatus());
    }

    public function test_publishing_from_the_list_promotes_the_draft(): void
    {
        (new PageContentStore)->saveDraft('home', [[
            'id' => 'hero', 'type' => 'hero', 'label' => 'Hero',
            'active' => true, 'data' => ['heading' => 'From the list'],
        ]], 'Tester');

        $this->post('/cms/pages/1/publish-now')->assertRedirect();

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('sections.0.data.heading', 'From the list'));
        $this->assertCount(1, (new PageContentStore)->revisions('home')['rows']);
    }

    public function test_duplicating_creates_an_independent_draft_copy(): void
    {
        $this->post('/cms/pages/1/duplicate')->assertRedirect();

        $copy = Page::where('slug', 'home-copy')->first();

        $this->assertNotNull($copy);
        $this->assertSame('draft', $copy->status);
        $this->assertSame('/home-copy', $copy->url);
        $this->assertSame(Page::max('cms_id'), $copy->cms_id);
        $this->assertStringEndsWith('(copy)', $copy->title);
        $this->assertSame([], $copy->published);
        $this->assertNotEmpty($copy->draft);
        $this->assertSame(0, PageRevision::where('page_id', $copy->id)->count());
    }

    public function test_duplicating_twice_does_not_collide(): void
    {
        $before = Page::count();

        $this->post('/cms/pages/1/duplicate')->assertRedirect();
        $this->post('/cms/pages/1/duplicate')->assertRedirect();

        $this->assertTrue(Page::where('slug', 'home-copy')->exists());
        $this->assertTrue(Page::where('slug', 'home-copy-2')->exists());
        $this->assertSame($before + 2, Page::count());
    }

    public function test_a_duplicate_is_editable_and_does_not_touch_the_original(): void
    {
        $original = (new PageContentStore)->document('home');

        $this->post('/cms/pages/1/duplicate')->assertRedirect();
        $this->get('/cms/pages/'.Page::where('slug', 'home-copy')->value('cms_id').'/edit')->assertOk();

        (new PageContentStore)->saveDraft('home-copy', [[
            'id' => 'hero', 'type' => 'hero', 'label' => 'Hero',
            'active' => true, 'data' => ['heading' => 'Only in the copy'],
        ]], 'Tester');

        $this->assertSame($original, (new PageContentStore)->document('home'));
    }

    public function test_the_list_reports_archived_pages(): void
    {
        $this->post('/cms/pages/1/archive')->assertRedirect();

        $this->get('/cms/pages')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('pages.0.status', 'archived'));
    }

    public function test_the_actions_404_for_an_unknown_page(): void
    {
        foreach (['unpublish', 'archive', 'unarchive', 'duplicate', 'publish-now'] as $action) {
            $this->post("/cms/pages/99/{$action}")->assertNotFound();
        }
    }
}
