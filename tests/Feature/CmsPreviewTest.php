<?php

namespace Tests\Feature;

use App\Content\PageContentStore;
use App\Models\Page;
use App\Models\PageRevision;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CmsPreviewTest extends TestCase
{
    private function sections(string $heading): array
    {
        return [[
            'id' => 'hero',
            'type' => 'hero',
            'label' => 'Hero banner',
            'active' => true,
            'data' => ['heading' => $heading],
        ]];
    }

    private function heading(AssertableInertia $page): ?string
    {
        return $page->toArray()['props']['sections'][0]['data']['heading'] ?? null;
    }

    public function test_preview_renders_the_draft_not_the_published_tree(): void
    {
        $store = new PageContentStore;
        $store->saveDraft('home', $this->sections('Draft only'), 'Tester');

        $this->get('/cms/pages/1/preview')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('AgentFinder')
                ->where('preview.mode', 'draft')
                ->where('sections.0.data.heading', 'Draft only'));

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $this->assertNotSame('Draft only', $this->heading($p)));
    }

    public function test_preview_falls_back_to_published_when_there_is_no_draft(): void
    {
        $this->assertNull((new PageContentStore)->document('home')['draft']);

        $this->get('/cms/pages/1/preview')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('preview.mode', 'published')
                ->has('sections', 8));
    }

    public function test_preview_drops_hidden_and_illegal_blocks(): void
    {
        Page::where('slug', 'home')->update(['draft' => [
            ['id' => 'a', 'type' => 'hero', 'label' => 'Hero', 'active' => true, 'data' => []],
            ['id' => 'b', 'type' => 'cta', 'label' => 'Hidden', 'active' => false, 'data' => []],
            ['id' => 'c', 'type' => 'legacy-widget', 'label' => 'Gone', 'active' => true, 'data' => []],
        ]]);

        $this->get('/cms/pages/1/preview')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->has('sections', 1)
                ->where('sections.0.id', 'a'));
    }

    public function test_preview_is_never_cached(): void
    {
        config(['app.debug' => false]);

        $this->get('/')->assertOk();

        (new PageContentStore)->saveDraft('home', $this->sections('Fresh draft'), 'Tester');

        $this->get('/cms/pages/1/preview')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('sections.0.data.heading', 'Fresh draft'));
    }

    public function test_a_revision_can_be_previewed(): void
    {
        $this->post('/cms/pages/1/publish', ['sections' => $this->sections('Version one')])->assertRedirect();
        $this->post('/cms/pages/1/publish', ['sections' => $this->sections('Version two')])->assertRedirect();

        $this->get('/cms/pages/1/preview/1')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('AgentFinder')
                ->where('preview.mode', 'revision')
                ->where('preview.n', 1)
                ->where('sections.0.data.heading', 'Version one'));
    }

    public function test_an_unknown_revision_cannot_be_previewed(): void
    {
        $this->get('/cms/pages/1/preview/99')->assertNotFound();
    }

    public function test_an_unknown_page_cannot_be_previewed(): void
    {
        $this->get('/cms/pages/99/preview')->assertNotFound();
        $this->get('/cms/pages/abc/preview')->assertNotFound();
    }

    public function test_a_revision_preview_drops_retired_block_types(): void
    {
        $page = Page::where('slug', 'home')->first();

        PageRevision::create([
            'page_id' => $page->id,
            'n' => 1,
            'action' => 'publish',
            'by' => 'Tester',
            'sections' => [
                ['id' => 'a', 'type' => 'hero', 'label' => 'Hero', 'active' => true, 'data' => []],
                ['id' => 'b', 'type' => 'legacy-widget', 'label' => 'Gone', 'active' => true, 'data' => []],
            ],
        ]);

        $this->get('/cms/pages/1/preview/1')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->has('sections', 1));
    }
}
