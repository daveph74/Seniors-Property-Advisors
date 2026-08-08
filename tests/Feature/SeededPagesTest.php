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
    /**
     * The whole section list per page, not just the first one. Every page used to be its one
     * section plus the shared call to action, which left six pages two blocks deep with nothing
     * linking to anything else — so the second block, and what it links on to, is now part of what
     * these pages are and is asserted as such.
     */
    public static function pages(): array
    {
        return [
            'how it works' => ['how-it-works', 5, 'How it works', ['process-steps', 'trust-cards', 'cta']],
            'why agent finder' => ['why-agent-finder', 12, 'Why Agent Finder', ['why-list', 'section', 'cta']],
            'compare agents' => ['compare-agents', 13, 'Compare agents', ['agent-compare', 'trust-cards', 'cta']],
            'for families' => ['for-families', 14, 'For families', ['family', 'section', 'cta']],
            'contact' => ['contact', 18, 'Contact', ['contact-form', 'section', 'cta']],
            'blog' => ['blog', 16, 'Blog', ['blog-list', 'cta']],
            'faqs' => ['faqs', 17, 'FAQs', ['faq-list', 'section', 'cta']],
        ];
    }

    #[DataProvider('pages')]
    public function test_it_is_seeded_published_with_its_sections_and_the_call_to_action(string $slug, int $cmsId, string $title, array $types): void
    {
        $page = Page::where('slug', $slug)->firstOrFail();

        $this->assertSame('published', $page->status);
        $this->assertSame("/{$slug}", $page->url);
        $this->assertSame($cmsId, $page->cms_id);
        $this->assertSame($title, $page->title);

        /* The call to action is part of the page's own tree rather than layout chrome, which is
           why it is the last entry on every one of them. */
        $this->assertSame($types, array_column($page->published, 'type'));
        $this->assertNotEmpty($page->published[0]['data']['heading']);
    }

    #[DataProvider('pages')]
    public function test_a_reader_gets_it(string $slug, int $cmsId, string $title, array $types): void
    {
        $this->get("/{$slug}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('AgentFinder')
                ->where('title', $title)
                ->where('sections.0.type', $types[0])
                ->count('sections', count($types)));
    }

    /**
     * Every page must nominate exactly one heading as its h1.
     *
     * Which heading is decided in React, so PHPUnit cannot see the rendered tag — what it can
     * check is the input that decision is made from. A page assembled from the block system has no
     * heading at the top level at all: the container carries the width and the words are the blocks
     * inside it, so `ownerOfTheH1` has to descend to find one. The legal pages are built that way,
     * and before it did they had no h1 anywhere.
     *
     * @return array<string, array{0: string}>
     */
    public static function everyPage(): array
    {
        /* Not resource_path(): a data provider runs before the application is booted. */
        $files = glob(__DIR__.'/../../resources/content/pages/*.json') ?: [];

        return collect($files)
            ->mapWithKeys(fn ($path) => [basename($path, '.json') => [basename($path, '.json')]])
            ->all();
    }

    /**
     * Seeded content has to be content the CMS will accept back.
     *
     * `ContentSeeder` writes these trees straight into the column, so nothing they contain is ever
     * checked against `SaveSectionsRequest` — which meant a seed file could hold a page that
     * rendered perfectly and then refused to publish the moment an editor opened it and pressed the
     * button. That is exactly what happened: every block nested inside a section container was
     * written without a `label`, which is required at every depth, so six pages were unpublishable
     * and nothing said so until somebody tried.
     */
    #[DataProvider('everyPage')]
    public function test_an_editor_can_publish_it_unchanged(string $slug): void
    {
        $page = Page::where('slug', $slug)->firstOrFail();

        $this->post("/cms/pages/{$page->cms_id}/publish", ['sections' => $page->published])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        /* Publishing an unchanged tree is not a no-op when the page is not live: there is nothing
           to promote, but the status still has to move. The builder's Publish button was disabled
           on an empty diff alone, so a page in this state could never be published from there. */
        $this->assertSame('published', $page->refresh()->status);
    }

    #[DataProvider('everyPage')]
    public function test_it_has_a_heading_that_can_be_the_h1(string $slug): void
    {
        $sections = Page::where('slug', $slug)->value('published') ?? [];

        $owner = function (array $blocks) use (&$owner): bool {
            foreach ($blocks as $block) {
                if (($block['active'] ?? true) === false) {
                    continue;
                }

                if (in_array($block['type'] ?? null, ['hero', 'hero-full'], true)) {
                    return true;
                }

                if (filled($block['data']['heading'] ?? null) || filled($block['data']['headingEm'] ?? null)) {
                    return true;
                }

                if ($owner($block['children'] ?? [])) {
                    return true;
                }
            }

            return false;
        };

        $this->assertTrue($owner($sections), "/{$slug} has no heading anywhere, so it would render with no h1");
    }

    /**
     * The only internal links used to be the menu and the footer — no page pointed at another from
     * its own copy, which is thin for a reader following a train of thought and thinner still for
     * a crawler working out what relates to what.
     */
    public function test_every_page_links_somewhere_else_on_the_site(): void
    {
        /* /blog is exempt: its links are the article list, which is built from the database at
           render time rather than written into the section tree. */
        foreach (array_diff(array_column(self::pages(), 0), ['blog']) as $slug) {
            $tree = json_encode(Page::where('slug', $slug)->value('published'), JSON_UNESCAPED_SLASHES);

            preg_match_all('#"href":"(/[a-z0-9/-]*)"#', $tree, $found);

            $internal = array_values(array_filter(
                array_unique($found[1]),
                fn ($href) => $href !== '/'.$slug && $href !== '/',
            ));

            $this->assertNotEmpty($internal, "/{$slug} links nowhere else on the site");
        }
    }

    /**
     * It existed so the full-bleed hero could be compared against the boxed one. The home page
     * carries that hero now, so the comparison has no reader left — archived rather than deleted,
     * because the sections are still the record of what was decided and the CMS can restore them.
     */
    public function test_the_hero_preview_is_archived_and_off_the_website(): void
    {
        $page = Page::where('slug', 'hero-preview')->firstOrFail();

        $this->assertSame('archived', $page->status);
        $this->assertSame(['hero-full', 'trust-cards', 'cta'], array_column($page->published, 'type'));

        $this->get('/hero-preview')->assertNotFound();

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
            ->concat(collect($globals['nav']['links'])->flatMap(fn ($l) => $l['children'] ?? []))
            ->concat(collect($globals['footer']['columns'])->flatMap(fn ($c) => $c['links'] ?? []))
            /* The small print row as well. It was outside this sweep, which is how Privacy, Terms
               and Complaints sat on a dead `#` without anything noticing — and those are the three
               a reader is most entitled to find. */
            ->concat(collect($globals['footer']['links'] ?? []))
            ->pluck('href')
            ->filter(fn ($href) => is_string($href) && str_starts_with($href, '/'))
            ->unique()
            ->values();

        $this->assertNotEmpty($paths, 'the menu names no pages at all');

        foreach ($paths as $path) {
            $this->get($path)->assertOk("{$path} is in the menu but does not answer");
        }
    }

    /**
     * The consent box on the contact form offers the privacy policy, and finds it through a
     * setting that names a published page. Nothing joined those up, so the form asked people to
     * agree to how their details would be handled with nowhere to go and read it.
     */
    public function test_the_consent_box_can_point_at_a_privacy_policy(): void
    {
        $this->get('/contact')->assertOk()->assertInertia(function (AssertableInertia $p) {
            $this->assertSame('/privacy-policy', $p->toArray()['props']['site']['privacyUrl']);
        });

        $this->get('/privacy-policy')->assertOk();
    }
}
