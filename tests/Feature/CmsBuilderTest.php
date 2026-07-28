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

    private function section(array $sectionChildren, array $data = ['width' => 'standard']): array
    {
        return [[
            'id' => 'section-1',
            'type' => 'section',
            'label' => 'Section',
            'active' => true,
            'data' => $data,
            'children' => $sectionChildren,
        ]];
    }

    private function nested(array $child = []): array
    {
        return $this->section([array_merge([
            'id' => 'hero-2',
            'type' => 'hero',
            'label' => 'Hero banner',
            'active' => true,
            'data' => ['heading' => 'Nested heading'],
        ], $child)]);
    }

    private function rowTree(?array $rowChildren = null): array
    {
        return $this->section([[
            'id' => 'row-2',
            'type' => 'row',
            'label' => 'Row',
            'active' => true,
            'data' => [],
            'children' => $rowChildren ?? [[
                'id' => 'column-3',
                'type' => 'column',
                'label' => 'Column 1',
                'active' => true,
                'data' => [],
                'children' => [[
                    'id' => 'heading-4',
                    'type' => 'heading',
                    'label' => 'Heading',
                    'active' => true,
                    'data' => ['heading' => 'Nested deep', 'level' => 'h3'],
                ]],
            ]],
        ]]);
    }

    private function deepTree(?array $innerColumnChildren = null): array
    {
        return $this->rowTree([[
            'id' => 'column-3',
            'type' => 'column',
            'label' => 'Column 1',
            'active' => true,
            'data' => [],
            'children' => [[
                'id' => 'row-4',
                'type' => 'row',
                'label' => 'Row',
                'active' => true,
                'data' => [],
                'children' => [[
                    'id' => 'column-5',
                    'type' => 'column',
                    'label' => 'Column 1',
                    'active' => true,
                    'data' => [],
                    'children' => $innerColumnChildren ?? [[
                        'id' => 'heading-6',
                        'type' => 'heading',
                        'label' => 'Heading',
                        'active' => true,
                        'data' => ['heading' => 'Six levels down'],
                    ]],
                ]],
            ]],
        ]]);
    }

    public function test_it_saves_and_publishes_a_row_column_tree(): void
    {
        $this->post('/cms/pages/1/publish', ['sections' => $this->rowTree()])->assertRedirect();

        $store = new PageContentStore;
        $document = $store->document('home');
        $row = $document['published'][0]['children'][0];

        $this->assertSame('row', $row['type']);
        $this->assertSame('column', $row['children'][0]['type']);
        $this->assertSame('heading', $row['children'][0]['children'][0]['type']);
        $this->assertSame('Nested deep', $row['children'][0]['children'][0]['data']['heading']);
        $this->assertSame('heading', $store->revisions('home')[0]['sections'][0]['children'][0]['children'][0]['children'][0]['type']);

        $this->get('/')->assertInertia(function ($page) {
            $sections = $page->toArray()['props']['sections'];

            $this->assertSame('heading', $sections[0]['children'][0]['children'][0]['children'][0]['type']);
        });
    }

    public function test_it_rejects_a_column_at_page_level(): void
    {
        $this->post('/cms/pages/1/draft', ['sections' => [[
            'id' => 'column-1',
            'type' => 'column',
            'label' => 'Content',
            'active' => true,
            'data' => [],
            'children' => [],
        ]]])->assertSessionHasErrors('sections.0.type');

        $this->assertNull((new PageContentStore)->document('home')['draft']);
    }

    public function test_it_rejects_a_row_inside_a_row(): void
    {
        $this->post('/cms/pages/1/draft', ['sections' => $this->rowTree([[
            'id' => 'row-3',
            'type' => 'row',
            'label' => 'Row',
            'active' => true,
            'data' => [],
            'children' => [],
        ]])])->assertSessionHasErrors('sections.0.children.0.children.0.type');

        $this->assertNull((new PageContentStore)->document('home')['draft']);
    }

    public function test_it_allows_a_row_inside_a_column(): void
    {
        $this->post('/cms/pages/1/draft', ['sections' => $this->deepTree()])
            ->assertSessionHasNoErrors()
            ->assertRedirect();
    }

    public function test_it_saves_and_publishes_the_full_six_level_chain(): void
    {
        $this->post('/cms/pages/1/publish', ['sections' => $this->deepTree()])->assertRedirect();

        $store = new PageContentStore;
        $document = $store->document('home');

        $leaf = $document['published'][0]['children'][0]['children'][0]['children'][0]['children'][0]['children'][0];

        $this->assertSame('heading', $leaf['type']);
        $this->assertSame('Six levels down', $leaf['data']['heading']);

        $revision = $store->revisions('home')[0]['sections'][0];
        $this->assertSame(
            'heading',
            $revision['children'][0]['children'][0]['children'][0]['children'][0]['children'][0]['type'],
        );

        $this->get('/')->assertInertia(function ($page) {
            $sections = $page->toArray()['props']['sections'];
            $leaf = $sections[0]['children'][0]['children'][0]['children'][0]['children'][0]['children'][0];

            $this->assertSame('heading', $leaf['type']);
        });
    }

    public function test_it_validates_fields_at_the_deepest_level(): void
    {
        $this->post('/cms/pages/1/draft', ['sections' => $this->deepTree([[
            'id' => 'heading-6',
            'type' => 'heading',
            'label' => str_repeat('a', 200),
            'active' => true,
            'data' => [],
        ]])])->assertSessionHasErrors('sections.0.children.0.children.0.children.0.children.0.children.0.label');

        $this->assertNull((new PageContentStore)->document('home')['draft']);
    }

    public function test_it_rejects_a_third_level_of_row_nesting(): void
    {
        $this->post('/cms/pages/1/draft', ['sections' => $this->deepTree([[
            'id' => 'row-6',
            'type' => 'row',
            'label' => 'Row',
            'active' => true,
            'data' => [],
            'children' => [],
        ]])])->assertSessionHasErrors('sections.0.children.0.children.0.children.0.children.0.children.0.type');

        $this->assertNull((new PageContentStore)->document('home')['draft']);
    }

    public function test_it_rejects_children_on_a_leaf_at_the_bottom_of_the_chain(): void
    {
        $this->post('/cms/pages/1/draft', ['sections' => $this->deepTree([[
            'id' => 'heading-6',
            'type' => 'heading',
            'label' => 'Heading',
            'active' => true,
            'data' => [],
            'children' => [[
                'id' => 'cta-7',
                'type' => 'cta',
                'label' => 'Call to action',
                'active' => true,
                'data' => [],
            ]],
        ]])])->assertSessionHasErrors('sections.0.children.0.children.0.children.0.children.0.children.0.children');

        $this->assertNull((new PageContentStore)->document('home')['draft']);
    }

    public function test_it_rejects_a_leaf_as_a_direct_child_of_a_row(): void
    {
        $this->post('/cms/pages/1/draft', ['sections' => $this->rowTree([[
            'id' => 'heading-3',
            'type' => 'heading',
            'label' => 'Heading',
            'active' => true,
            'data' => [],
        ]])])->assertSessionHasErrors('sections.0.children.0.children.0.type');

        $this->assertNull((new PageContentStore)->document('home')['draft']);
    }

    public function test_it_rejects_a_column_as_a_direct_child_of_a_section(): void
    {
        $this->post('/cms/pages/1/draft', ['sections' => $this->section([[
            'id' => 'column-2',
            'type' => 'column',
            'label' => 'Column 1',
            'active' => true,
            'data' => [],
            'children' => [],
        ]])])->assertSessionHasErrors('sections.0.children.0.type');

        $this->assertNull((new PageContentStore)->document('home')['draft']);
    }

    public function test_it_rejects_children_on_a_leaf_block(): void
    {
        $this->post('/cms/pages/1/draft', ['sections' => $this->section([[
            'id' => 'heading-2',
            'type' => 'heading',
            'label' => 'Heading',
            'active' => true,
            'data' => [],
            'children' => [[
                'id' => 'cta-3',
                'type' => 'cta',
                'label' => 'Call to action',
                'active' => true,
                'data' => [],
            ]],
        ]])])->assertSessionHasErrors('sections.0.children.0.children');

        $this->assertNull((new PageContentStore)->document('home')['draft']);
    }

    public function test_it_saves_a_section_with_nested_children(): void
    {
        $this->post('/cms/pages/1/draft', ['sections' => $this->nested()])->assertRedirect();

        $document = (new PageContentStore)->document('home');

        $this->assertSame('section', $document['draft'][0]['type']);
        $this->assertCount(1, $document['draft'][0]['children']);
        $this->assertSame('hero', $document['draft'][0]['children'][0]['type']);
        $this->assertSame('Nested heading', $document['draft'][0]['children'][0]['data']['heading']);
    }

    public function test_publishing_keeps_nested_children(): void
    {
        $this->post('/cms/pages/1/publish', ['sections' => $this->nested()])->assertRedirect();

        $store = new PageContentStore;
        $document = $store->document('home');

        $this->assertNull($document['draft']);
        $this->assertCount(1, $document['published'][0]['children']);
        $this->assertCount(1, $store->revisions('home')[0]['sections'][0]['children']);

        $this->get('/')->assertInertia(function ($page) {
            $sections = $page->toArray()['props']['sections'];

            $this->assertCount(1, $sections);
            $this->assertSame('hero', $sections[0]['children'][0]['type']);
        });
    }

    public function test_it_rejects_a_section_nested_inside_a_section(): void
    {
        $this->post('/cms/pages/1/draft', [
            'sections' => $this->nested(['type' => 'section', 'label' => 'Section']),
        ])->assertSessionHasErrors('sections.0.children.0.type');

        $this->assertNull((new PageContentStore)->document('home')['draft']);
    }

    public function test_it_rejects_an_unknown_child_type(): void
    {
        $this->post('/cms/pages/1/draft', [
            'sections' => $this->nested(['type' => 'raw-html']),
        ])->assertSessionHasErrors('sections.0.children.0.type');

        $this->assertNull((new PageContentStore)->document('home')['draft']);
    }

    public function test_it_rejects_a_grandchild_list(): void
    {
        $this->post('/cms/pages/1/draft', [
            'sections' => $this->nested(['children' => [[
                'id' => 'cta-3',
                'type' => 'cta',
                'label' => 'Call to action',
                'active' => true,
                'data' => [],
            ]]]),
        ])->assertSessionHasErrors('sections.0.children.0.children');

        $this->assertNull((new PageContentStore)->document('home')['draft']);
    }

    public function test_a_section_persists_its_width(): void
    {
        $this->post('/cms/pages/1/publish', [
            'sections' => $this->section([[
                'id' => 'heading-3',
                'type' => 'heading',
                'label' => 'Heading',
                'active' => true,
                'data' => ['heading' => 'Narrow'],
            ]], ['width' => 'narrow']),
        ])->assertRedirect();

        $document = (new PageContentStore)->document('home');

        $this->assertSame('narrow', $document['published'][0]['data']['width']);

        $this->get('/')->assertInertia(function ($page) {
            $sections = $page->toArray()['props']['sections'];

            $this->assertSame('narrow', $sections[0]['data']['width']);
        });
    }

    public function test_a_leaf_section_is_saved_without_a_children_key(): void
    {
        $this->post('/cms/pages/1/draft', ['sections' => $this->sections()])->assertRedirect();

        $document = (new PageContentStore)->document('home');

        $this->assertArrayNotHasKey('children', $document['draft'][0]);
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
