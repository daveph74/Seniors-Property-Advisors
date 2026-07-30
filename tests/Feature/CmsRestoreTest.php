<?php

namespace Tests\Feature;

use App\Content\PageContentStore;
use App\Models\Page;
use App\Models\PageRevision;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CmsRestoreTest extends TestCase
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

    private function publishTwice(): void
    {
        $this->post('/cms/pages/1/publish', ['sections' => $this->sections('Version one')])->assertRedirect();
        $this->post('/cms/pages/1/publish', ['sections' => $this->sections('Version two')])->assertRedirect();
    }

    public function test_restoring_writes_the_snapshot_back_as_a_draft(): void
    {
        $this->publishTwice();

        $this->post('/cms/pages/1/restore/1')->assertRedirect();

        $document = (new PageContentStore)->document('home');

        $this->assertSame('Version one', $document['draft'][0]['data']['heading']);
        $this->assertSame('Version two', $document['published'][0]['data']['heading']);
    }

    public function test_restoring_leaves_the_public_page_untouched(): void
    {
        $this->publishTwice();
        $this->post('/cms/pages/1/restore/1')->assertRedirect();

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('sections.0.data.heading', 'Version two'));
    }

    public function test_restoring_creates_no_revision(): void
    {
        $this->publishTwice();
        $this->assertCount(2, (new PageContentStore)->revisions('home'));

        $this->post('/cms/pages/1/restore/1')->assertRedirect();

        $this->assertCount(2, (new PageContentStore)->revisions('home'));
    }

    public function test_a_restored_draft_can_then_be_published(): void
    {
        $this->publishTwice();
        $this->post('/cms/pages/1/restore/1')->assertRedirect();

        $sections = (new PageContentStore)->document('home')['draft'];

        $this->post('/cms/pages/1/publish', ['sections' => $sections])->assertRedirect();

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('sections.0.data.heading', 'Version one'));
        $this->assertCount(3, (new PageContentStore)->revisions('home'));
    }

    public function test_a_revision_holding_a_retired_block_type_still_restores_and_re_saves(): void
    {
        $page = Page::where('slug', 'home')->first();

        PageRevision::create([
            'page_id' => $page->id,
            'n' => 1,
            'action' => 'publish',
            'by' => 'Tester',
            'sections' => [
                ['id' => 'a', 'type' => 'hero', 'label' => 'Hero', 'active' => true, 'data' => ['heading' => 'Kept']],
                ['id' => 'b', 'type' => 'legacy-widget', 'label' => 'Gone', 'active' => true, 'data' => []],
            ],
        ]);

        $this->post('/cms/pages/1/restore/1')->assertRedirect();

        $draft = (new PageContentStore)->document('home')['draft'];

        $this->assertCount(1, $draft);
        $this->assertSame('hero', $draft[0]['type']);

        $this->post('/cms/pages/1/draft', ['sections' => $draft])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    public function test_restoring_keeps_blocks_the_editor_had_hidden(): void
    {
        $page = Page::where('slug', 'home')->first();

        PageRevision::create([
            'page_id' => $page->id,
            'n' => 1,
            'action' => 'publish',
            'by' => 'Tester',
            'sections' => [
                ['id' => 'a', 'type' => 'hero', 'label' => 'Hero', 'active' => true, 'data' => []],
                ['id' => 'b', 'type' => 'cta', 'label' => 'Hidden', 'active' => false, 'data' => []],
            ],
        ]);

        $this->post('/cms/pages/1/restore/1')->assertRedirect();

        $draft = (new PageContentStore)->document('home')['draft'];

        $this->assertCount(2, $draft);
        $this->assertFalse($draft[1]['active']);
    }

    public function test_restoring_overwrites_an_existing_draft(): void
    {
        $this->publishTwice();

        (new PageContentStore)->saveDraft('home', $this->sections('Work in progress'), 'Tester');

        $this->post('/cms/pages/1/restore/1')->assertRedirect();

        $this->assertSame('Version one', (new PageContentStore)->document('home')['draft'][0]['data']['heading']);
    }

    public function test_the_builder_receives_the_restored_sections(): void
    {
        $this->publishTwice();
        $this->post('/cms/pages/1/restore/1')->assertRedirect();

        $this->get('/cms/pages/1/edit')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('sections.0.data.heading', 'Version one'));
    }

    public function test_unknown_revisions_and_pages_cannot_be_restored(): void
    {
        $this->post('/cms/pages/1/restore/99')->assertNotFound();
        $this->post('/cms/pages/99/restore/1')->assertNotFound();
    }
}
