<?php

namespace Tests\Feature;

use App\Content\PageContentStore;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    public function test_the_home_page_renders_the_agent_finder_component(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('AgentFinder'));
    }

    public function test_it_passes_every_section_in_content_order(): void
    {
        $this->get('/')->assertInertia(function ($page) {
            $types = collect($page->toArray()['props']['sections'])->pluck('type')->all();

            $this->assertSame([
                'hero-full',
                'trust-cards',
                'text-image',
                'blog-list',
                'cta',
            ], $types);
        });
    }

    public function test_it_passes_global_chrome_content(): void
    {
        $this->get('/')->assertInertia(function ($page) {
            $globals = $page->toArray()['props']['globals'];

            $this->assertSame('1300 277 228', $globals['phone']['label']);
            $this->assertContains('How it works', array_column($globals['nav']['links'], 'label'));

            /* Blog is nested under Resources, so the menu fits the content column. */
            $resources = collect($globals['nav']['links'])->firstWhere('label', 'Resources');

            $this->assertContains('/blog', array_column($resources['children'], 'href'));
            $this->assertCount(3, $globals['footer']['columns']);
        });
    }

    public function test_it_drops_inactive_and_unregistered_sections(): void
    {
        $store = new PageContentStore;

        $sections = (fn () => $this->renderable([
            ['type' => 'hero', 'active' => true],
            ['type' => 'cta', 'active' => false],
            ['type' => 'raw-html', 'active' => true],
        ]))->call($store);

        $this->assertSame([['type' => 'hero', 'active' => true]], $sections);
    }

    public function test_it_filters_children_and_reindexes_them(): void
    {
        $store = new PageContentStore;

        $sections = (fn () => $this->renderable([[
            'type' => 'section',
            'active' => true,
            'children' => [
                ['type' => 'cta', 'active' => false],
                ['type' => 'hero', 'active' => true],
                ['type' => 'raw-html', 'active' => true],
                ['type' => 'column', 'active' => true],
            ],
        ]]))->call($store);

        $this->assertSame([[
            'type' => 'section',
            'active' => true,
            'children' => [['type' => 'hero', 'active' => true]],
        ]], $sections);
    }

    public function test_it_filters_row_children_to_columns(): void
    {
        $store = new PageContentStore;

        $sections = (fn () => $this->renderable([[
            'type' => 'section',
            'active' => true,
            'children' => [[
                'type' => 'row',
                'active' => true,
                'children' => [
                    ['type' => 'hero', 'active' => true],
                    [
                        'type' => 'column',
                        'active' => true,
                        'children' => [
                            ['type' => 'cta', 'active' => false],
                            ['type' => 'heading', 'active' => true],
                            ['type' => 'column', 'active' => true],
                        ],
                    ],
                ],
            ]],
        ]]))->call($store);

        $this->assertSame([[
            'type' => 'section',
            'active' => true,
            'children' => [[
                'type' => 'row',
                'active' => true,
                'children' => [[
                    'type' => 'column',
                    'active' => true,
                    'children' => [['type' => 'heading', 'active' => true]],
                ]],
            ]],
        ]], $sections);
    }

    public function test_it_drops_a_row_at_page_level(): void
    {
        $store = new PageContentStore;

        $sections = (fn () => $this->renderable([['type' => 'row', 'active' => true]]))->call($store);

        $this->assertSame([], $sections);
    }

    public function test_a_seeded_section_keeps_its_row_and_column(): void
    {
        $store = new PageContentStore;

        $sections = (fn () => $this->renderable([[
            'type' => 'section',
            'active' => true,
            'children' => [[
                'type' => 'row',
                'active' => true,
                'children' => [[
                    'type' => 'column',
                    'active' => true,
                    'children' => [
                        ['type' => 'eyebrow', 'active' => true],
                        ['type' => 'heading', 'active' => true],
                        ['type' => 'button', 'active' => true],
                    ],
                ]],
            ]],
        ]]))->call($store);

        $this->assertSame([[
            'type' => 'section',
            'active' => true,
            'children' => [[
                'type' => 'row',
                'active' => true,
                'children' => [[
                    'type' => 'column',
                    'active' => true,
                    'children' => [
                        ['type' => 'eyebrow', 'active' => true],
                        ['type' => 'heading', 'active' => true],
                        ['type' => 'button', 'active' => true],
                    ],
                ]],
            ]],
        ]], $sections);
    }

    public function test_it_keeps_a_row_nested_two_deep(): void
    {
        $store = new PageContentStore;

        $sections = (fn () => $this->renderable([[
            'type' => 'section',
            'active' => true,
            'children' => [[
                'type' => 'row',
                'active' => true,
                'children' => [[
                    'type' => 'column',
                    'active' => true,
                    'children' => [['type' => 'row', 'active' => true, 'children' => []]],
                ]],
            ]],
        ]]))->call($store);

        $this->assertSame([[
            'type' => 'section',
            'active' => true,
            'children' => [[
                'type' => 'row',
                'active' => true,
                'children' => [[
                    'type' => 'column',
                    'active' => true,
                    'children' => [['type' => 'row', 'active' => true, 'children' => []]],
                ]],
            ]],
        ]], $sections);
    }

    public function test_it_drops_a_row_nested_three_deep(): void
    {
        $store = new PageContentStore;

        $sections = (fn () => $this->renderable([[
            'type' => 'section',
            'active' => true,
            'children' => [[
                'type' => 'row',
                'active' => true,
                'children' => [[
                    'type' => 'column',
                    'active' => true,
                    'children' => [[
                        'type' => 'row',
                        'active' => true,
                        'children' => [[
                            'type' => 'column',
                            'active' => true,
                            'children' => [
                                ['type' => 'heading', 'active' => true],
                                ['type' => 'row', 'active' => true, 'children' => []],
                            ],
                        ]],
                    ]],
                ]],
            ]],
        ]]))->call($store);

        $innerColumn = $sections[0]['children'][0]['children'][0]['children'][0]['children'][0];

        $this->assertSame([['type' => 'heading', 'active' => true]], $innerColumn['children']);
    }

    public function test_it_drops_a_column_at_page_level(): void
    {
        $store = new PageContentStore;

        $sections = (fn () => $this->renderable([['type' => 'column', 'active' => true]]))->call($store);

        $this->assertSame([], $sections);
    }

    public function test_leaf_sections_gain_no_children_key(): void
    {
        $store = new PageContentStore;

        $sections = (fn () => $this->renderable([['type' => 'hero', 'active' => true]]))->call($store);

        $this->assertArrayNotHasKey('children', $sections[0]);
    }
}
