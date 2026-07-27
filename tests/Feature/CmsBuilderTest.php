<?php

namespace Tests\Feature;

use App\Content\PageContentStore;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CmsBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        File::deleteDirectory(storage_path('app/content'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/content'));

        parent::tearDown();
    }

    private function sections(string $heading = 'Edited heading'): array
    {
        return [[
            'id' => 'hero',
            'type' => 'hero',
            'label' => 'Hero banner',
            'active' => true,
            'data' => ['heading' => $heading],
        ]];
    }

    public function test_the_builder_receives_the_real_home_sections(): void
    {
        $this->get('/cms/pages/1/edit')->assertOk()->assertInertia(function ($page) {
            $props = $page->toArray()['props'];

            $this->assertSame('Cms/Pages/Builder', $page->toArray()['component']);
            $this->assertCount(7, $props['sections']);
            $this->assertSame('hero', $props['sections'][0]['type']);
            $this->assertSame('', $props['page']['slug']);
            $this->assertSame('published', $props['page']['status']);
            $this->assertFalse($props['page']['hasDraft']);
        });
    }

    public function test_design_only_pages_receive_no_sections(): void
    {
        foreach ([2, 5, 9] as $id) {
            $this->get("/cms/pages/{$id}/edit")->assertOk()->assertInertia(function ($page) {
                $this->assertArrayNotHasKey('sections', $page->toArray()['props']);
            });
        }
    }

    public function test_saving_a_draft_does_not_change_the_public_page(): void
    {
        $this->post('/cms/pages/1/draft', ['sections' => $this->sections()])->assertRedirect();

        $store = new PageContentStore;
        $document = $store->document('home');

        $this->assertCount(1, $document['draft']);
        $this->assertCount(7, $document['published']);

        $this->get('/')->assertInertia(function ($page) {
            $sections = $page->toArray()['props']['sections'];
            $this->assertCount(7, $sections);
            $this->assertSame('Independent advice.', $sections[0]['data']['heading']);
        });
    }

    public function test_publishing_promotes_the_draft_and_writes_one_revision(): void
    {
        $this->post('/cms/pages/1/publish', ['sections' => $this->sections()])->assertRedirect();

        $store = new PageContentStore;
        $document = $store->document('home');

        $this->assertNull($document['draft']);
        $this->assertCount(1, $document['published']);
        $this->assertNotNull($document['published_at']);
        $this->assertCount(1, $store->revisions('home'));

        $this->get('/')->assertInertia(function ($page) {
            $sections = $page->toArray()['props']['sections'];
            $this->assertCount(1, $sections);
            $this->assertSame('Edited heading', $sections[0]['data']['heading']);
        });
    }

    public function test_it_rejects_a_section_type_outside_the_registry(): void
    {
        $sections = $this->sections();
        $sections[0]['type'] = 'raw-html';

        $this->post('/cms/pages/1/draft', ['sections' => $sections])
            ->assertSessionHasErrors('sections.0.type');

        $this->assertNull((new PageContentStore)->document('home')['draft']);
    }

    public function test_it_strips_markup_from_saved_strings(): void
    {
        $this->post('/cms/pages/1/draft', [
            'sections' => $this->sections('<script>alert(1)</script>Clean'),
        ])->assertRedirect();

        $document = (new PageContentStore)->document('home');

        $this->assertSame('alert(1)Clean', $document['draft'][0]['data']['heading']);
    }

    public function test_an_unknown_page_id_cannot_be_saved(): void
    {
        $this->post('/cms/pages/2/draft', ['sections' => $this->sections()])->assertNotFound();
    }
}
