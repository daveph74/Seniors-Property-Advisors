<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Setting;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * How it works ships as default content rather than as a hardcoded page, so every word of it
 * is editable. These guard the shape a reader depends on; the copy itself is free to change.
 */
class HowItWorksPageTest extends TestCase
{
    /**
     * Pages the menu names but the seed does not ship. `pages:scaffold` creates these, and
     * nothing in the documented setup runs it — so a fresh install really does offer two menu
     * items that 404. That predates the pages below and is recorded in docs/TODO.md; it is
     * listed here rather than filtered out silently, so a third dead link fails this test.
     */
    private const SCAFFOLD_ONLY = ['/faqs', '/blog'];

    /**
     * The menu used to scroll to home-page anchors — #how, #why — so these pages could be
     * published and still be unreachable from the site. Every menu item that names a page
     * must lead to one that answers.
     */
    public function test_every_menu_link_to_a_page_reaches_a_published_one(): void
    {
        $globals = Setting::where('key', 'globals')->value('value');

        $paths = collect($globals['nav']['links'])
            ->concat(collect($globals['footer']['columns'])->flatMap(fn ($c) => $c['links'] ?? []))
            ->pluck('href')
            ->filter(fn ($href) => is_string($href) && str_starts_with($href, '/'))
            ->reject(fn ($href) => in_array($href, self::SCAFFOLD_ONLY, true))
            ->unique()
            ->values();

        $this->assertNotEmpty($paths, 'the menu names no pages at all');

        foreach ($paths as $path) {
            $this->get($path)->assertOk("{$path} is in the menu but does not answer");
        }
    }

    public function test_it_is_seeded_published_into_the_existing_draft(): void
    {
        $page = Page::where('slug', 'how-it-works')->firstOrFail();

        $this->assertSame('published', $page->status);
        $this->assertSame('/how-it-works', $page->url);
        /* The scaffold command already made page 5; the seed fills it rather than adding a second. */
        $this->assertSame(5, $page->cms_id);
        $this->assertSame(1, Page::where('slug', 'how-it-works')->count());
    }

    public function test_a_reader_gets_the_hero_steps_and_call_to_action(): void
    {
        $this->get('/how-it-works')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('AgentFinder')
                ->where('title', 'How it works')
                ->where('sections.0.type', 'hero-full')
                ->where('sections.1.type', 'process-steps')
                ->where('sections.2.type', 'cta'));
    }

    /**
     * The hero leads for a reason: it is the only section here that renders an h1.
     * ProcessStepsSection hardcodes h2, so a page opening on the steps would have none.
     */
    public function test_the_hero_leads_and_carries_a_picture(): void
    {
        $hero = Page::where('slug', 'how-it-works')->firstOrFail()->published[0];

        $this->assertSame('hero-full', $hero['type']);
        $this->assertNotEmpty($hero['data']['heading']);
        $this->assertNotEmpty($hero['data']['image']['src']);
        $this->assertNotEmpty($hero['data']['image']['alt']);
    }

    public function test_the_four_steps_are_content_an_editor_can_change(): void
    {
        $steps = Page::where('slug', 'how-it-works')->firstOrFail()->published[1];

        $this->assertCount(4, $steps['data']['items']);
        $this->assertSame(['01', '02', '03', '04'], array_column($steps['data']['items'], 'num'));

        $this->post('/cms/pages/5/publish', ['sections' => [[
            'id' => 'how',
            'type' => 'process-steps',
            'label' => 'How it works',
            'active' => true,
            'data' => ['items' => [['num' => '01', 'title' => 'Reworded', 'body' => 'New body.']]],
        ]]])->assertRedirect()->assertSessionHasNoErrors();

        $this->get('/how-it-works')->assertInertia(fn (AssertableInertia $p) => $p
            ->where('sections.0.data.items.0.title', 'Reworded'));
    }
}
