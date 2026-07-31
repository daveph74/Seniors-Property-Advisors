<?php

namespace Tests\Feature;

use App\Content\PageContentStore;
use Tests\TestCase;

class CmsBuilderTest extends TestCase
{
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
        $this->assertSame('heading', $store->revisionSections('home', 1)[0]['children'][0]['children'][0]['children'][0]['type']);

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

        $revision = $store->revisionSections('home', 1)[0];
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
        $this->assertCount(1, $store->revisionSections('home', 1)[0]['children']);

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

    private const NEW_ELEMENTS = [
        'steps-strip', 'avatar-row', 'rating-stars', 'card-grid', 'step-grid',
        'checklist', 'benefit-list', 'trust-marks', 'stat-stamp', 'quote-card', 'info-card',
    ];

    private const SCOPE_SECTIONS = [
        'text-image', 'stat-row', 'testimonials', 'faq-list',
        'team-intro', 'contact-form', 'blog-list',
    ];

    public function test_the_new_elements_are_registered_block_types(): void
    {
        foreach (self::NEW_ELEMENTS as $type) {
            $this->assertContains($type, PageContentStore::BLOCK_TYPES, "{$type} is missing");
        }

        $this->assertCount(30, PageContentStore::BLOCK_TYPES);
    }

    public function test_the_scoped_section_types_are_registered(): void
    {
        foreach (self::SCOPE_SECTIONS as $type) {
            $this->assertContains($type, PageContentStore::BLOCK_TYPES, "{$type} is missing");
            $this->assertContains($type, PageContentStore::CHILD_TYPES['section']);
            $this->assertNotContains($type, PageContentStore::CHILD_TYPES['row']);
        }
    }

    public function test_the_new_elements_are_allowed_in_sections_and_columns_but_not_rows(): void
    {
        foreach (self::NEW_ELEMENTS as $type) {
            $this->assertContains($type, PageContentStore::CHILD_TYPES['section']);
            $this->assertContains($type, PageContentStore::CHILD_TYPES['column']);
            $this->assertNotContains($type, PageContentStore::CHILD_TYPES['row']);
        }
    }

    public function test_the_js_type_mirror_matches_php(): void
    {
        $js = file_get_contents(resource_path('js/sections/childTypes.js'));

        preg_match('/const BLOCK_TYPES = \[(.*?)\];/s', $js, $m);
        preg_match_all("/'([a-z-]+)'/", $m[1] ?? '', $found);

        $this->assertSame(
            PageContentStore::BLOCK_TYPES,
            $found[1],
            'childTypes.js has drifted from PageContentStore::BLOCK_TYPES',
        );
    }

    public function test_it_publishes_a_new_element_nested_in_a_column(): void
    {
        $this->post('/cms/pages/1/publish', ['sections' => $this->rowTree([[
            'id' => 'column-3',
            'type' => 'column',
            'label' => 'Column 1',
            'active' => true,
            'data' => [],
            'children' => [[
                'id' => 'card-grid-4',
                'type' => 'card-grid',
                'label' => 'Icon card grid',
                'active' => true,
                'data' => ['items' => [['icon' => 'shield', 'title' => 'Independent', 'body' => 'No commissions.']]],
            ]],
        ]])])->assertRedirect();

        $this->get('/')->assertInertia(function ($page) {
            $leaf = $page->toArray()['props']['sections'][0]['children'][0]['children'][0]['children'][0];

            $this->assertSame('card-grid', $leaf['type']);
            $this->assertSame('Independent', $leaf['data']['items'][0]['title']);
        });
    }

    public function test_a_near_miss_element_type_is_rejected(): void
    {
        $this->post('/cms/pages/1/draft', ['sections' => $this->section([[
            'id' => 'x-1',
            'type' => 'trust-card',
            'label' => 'Nope',
            'active' => true,
            'data' => [],
        ]])])->assertSessionHasErrors('sections.0.children.0.type');

        $this->assertNull((new PageContentStore)->document('home')['draft']);
    }

    public function test_a_column_persists_its_alignment(): void
    {
        $sections = $this->rowTree();
        $sections[0]['children'][0]['children'][0]['data'] = ['alignAcross' => 'center', 'alignDown' => 'spread'];

        $this->post('/cms/pages/1/publish', ['sections' => $sections])->assertRedirect();

        $column = (new PageContentStore)->document('home')['published'][0]['children'][0]['children'][0];

        $this->assertSame('center', $column['data']['alignAcross']);
        $this->assertSame('spread', $column['data']['alignDown']);

        $this->get('/')->assertInertia(function ($page) {
            $data = $page->toArray()['props']['sections'][0]['children'][0]['children'][0]['data'];

            $this->assertSame('center', $data['alignAcross']);
            $this->assertSame('spread', $data['alignDown']);
        });
    }

    public function test_an_element_persists_its_spacing(): void
    {
        $this->post('/cms/pages/1/publish', [
            'sections' => $this->section([[
                'id' => 'heading-2',
                'type' => 'heading',
                'label' => 'Heading',
                'active' => true,
                'data' => ['heading' => 'Spaced out', 'spaceAbove' => 'large', 'spaceBelow' => 'small'],
            ]]),
        ])->assertRedirect();

        $leaf = (new PageContentStore)->document('home')['published'][0]['children'][0];

        $this->assertSame('large', $leaf['data']['spaceAbove']);
        $this->assertSame('small', $leaf['data']['spaceBelow']);

        $this->get('/')->assertInertia(function ($page) {
            $data = $page->toArray()['props']['sections'][0]['children'][0]['data'];

            $this->assertSame('large', $data['spaceAbove']);
            $this->assertSame('small', $data['spaceBelow']);
        });
    }

    public function test_a_section_persists_its_anchor(): void
    {
        $sections = $this->section([[
            'id' => 'heading-2',
            'type' => 'heading',
            'label' => 'Heading',
            'active' => true,
            'data' => ['heading' => 'How it works'],
        ]]);
        $sections[0]['anchor'] = 'how';

        $this->post('/cms/pages/1/publish', ['sections' => $sections])->assertRedirect();

        $this->assertSame('how', (new PageContentStore)->document('home')['published'][0]['anchor']);

        $this->get('/')->assertInertia(function ($page) {
            $this->assertSame('how', $page->toArray()['props']['sections'][0]['anchor']);
        });
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
        $this->assertCount(1, $store->revisions('home')['rows']);

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
