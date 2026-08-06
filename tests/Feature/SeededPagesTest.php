<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\Faq;
use App\Models\Page;
use App\Models\Setting;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The menu pages ship as default content rather than as hardcoded page components, so
 * every word of them is editable. Composition follows docs/new-pages/pages/*.jsx: one section
 * per page, and that section carries the h1. These guard the shape; the copy is free to change.
 */
class SeededPagesTest extends TestCase
{
    public static function pages(): array
    {
        return [
            'how it works' => ['how-it-works', 5, 'How it works', 'process-steps'],
            'why agent finder' => ['why-agent-finder', 12, 'Why Agent Finder', 'why-list'],
            'compare agents' => ['compare-agents', 13, 'Compare agents', 'agent-compare'],
            'for families' => ['for-families', 14, 'For families', 'family'],
            'blog' => ['blog', 16, 'Blog', 'blog-list'],
            'faqs' => ['faqs', 17, 'FAQs', 'faq-list'],
        ];
    }

    #[DataProvider('pages')]
    public function test_it_is_seeded_published_with_its_section_and_the_call_to_action(string $slug, int $cmsId, string $title, string $type): void
    {
        $page = Page::where('slug', $slug)->firstOrFail();

        $this->assertSame('published', $page->status);
        $this->assertSame("/{$slug}", $page->url);
        $this->assertSame($cmsId, $page->cms_id);
        $this->assertSame($title, $page->title);

        /* The prototype's page file holds one section, but PublicLayout appends FinalCta to
           every public page, so the rendered page has two. The layout is chrome there and
           content here, which is why the call to action is part of the page's own tree. */
        $this->assertSame([$type, 'cta'], array_column($page->published, 'type'));
        $this->assertNotEmpty($page->published[0]['data']['heading']);
    }

    #[DataProvider('pages')]
    public function test_a_reader_gets_it(string $slug, int $cmsId, string $title, string $type): void
    {
        $this->get("/{$slug}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('AgentFinder')
                ->where('title', $title)
                ->where('sections.0.type', $type)
                ->where('sections.1.type', 'cta')
                ->count('sections', 2));
    }

    /**
     * The two-section page. It exists so the full-bleed hero can be compared against the boxed
     * one on the home page, which is why it is reachable but kept out of search and the menu.
     */
    public function test_the_hero_preview_carries_both_sections_and_stays_out_of_search(): void
    {
        $page = Page::where('slug', 'hero-preview')->firstOrFail();

        $this->assertSame('published', $page->status);
        $this->assertSame(['hero-full', 'trust-cards', 'cta'], array_column($page->published, 'type'));
        $this->assertTrue($page->seo['noindex']);

        $this->get('/hero-preview')
            ->assertOk()
            ->assertSee('name="robots" content="noindex, follow"', false);

        $globals = Setting::where('key', 'globals')->value('value');
        $linked = collect($globals['nav']['links'])
            ->concat(collect($globals['footer']['columns'])->flatMap(fn ($c) => $c['links'] ?? []))
            ->pluck('href');

        $this->assertNotContains('/hero-preview', $linked, 'a review page should not be in the menu');
    }

    /**
     * A client's first day has no articles and no questions. Both listings hide themselves when
     * empty, which is right for a section partway down a page and wrong when the section is the
     * page — /blog and /faqs rendered a call to action under no heading and with no h1 at all.
     */
    public static function emptyListings(): array
    {
        return [
            'blog' => ['/blog', 'blog-list'],
            'faqs' => ['/faqs', 'faq-list'],
        ];
    }

    /**
     * Whether the section then *renders* its heading is decided in React from the heading level,
     * so PHPUnit cannot see it — this asserts only that the page serves with the section and its
     * heading in the payload, which is what the component needs to do the rest. The rendering
     * itself, and the other half of the rule (an empty listing partway down a page still hides),
     * were checked in a browser.
     */
    #[DataProvider('emptyListings')]
    public function test_a_listing_page_still_serves_its_heading_with_nothing_to_list(string $path, string $type): void
    {
        BlogPost::query()->forceDelete();
        Faq::query()->forceDelete();

        $this->get($path)->assertOk()->assertInertia(function (AssertableInertia $p) use ($type) {
            $props = $p->toArray()['props'];

            $this->assertSame($type, $props['sections'][0]['type']);
            $this->assertNotEmpty($props['sections'][0]['data']['heading']);
            $this->assertSame([], $props['library']['posts'] ?? []);
            $this->assertSame([], $props['library']['faqs'] ?? []);
        });
    }

    public function test_the_steps_are_content_an_editor_can_change(): void
    {
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
            ->unique()
            ->values();

        $this->assertNotEmpty($paths, 'the menu names no pages at all');

        foreach ($paths as $path) {
            $this->get($path)->assertOk("{$path} is in the menu but does not answer");
        }
    }
}
