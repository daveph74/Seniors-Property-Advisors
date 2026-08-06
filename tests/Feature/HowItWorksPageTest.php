<?php

namespace Tests\Feature;

use App\Models\Page;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * How it works ships as default content rather than as a hardcoded page, so every word of it
 * is editable. These guard the shape a reader depends on; the copy itself is free to change.
 */
class HowItWorksPageTest extends TestCase
{
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
