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
                'hero',
                'trust-cards',
                'process-steps',
                'why-list',
                'agent-compare',
                'family',
                'cta',
            ], $types);
        });
    }

    public function test_it_passes_global_chrome_content(): void
    {
        $this->get('/')->assertInertia(function ($page) {
            $globals = $page->toArray()['props']['globals'];

            $this->assertSame('1300 277 228', $globals['phone']['label']);
            $this->assertCount(5, $globals['nav']['links']);
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
}
