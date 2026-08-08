<?php

namespace Tests\Feature;

use App\Models\Faq;
use App\Models\Page;
use App\Models\Setting;
use Tests\TestCase;

/**
 * The JSON-LD a page carries. Articles were the only thing describing themselves; these cover the
 * rest of the site.
 *
 * Read out of the delivered HTML rather than the Inertia props on purpose — a `<script>` that is
 * built correctly and then printed wrong helps nobody, and the blade has real logic in it now.
 */
class StructuredDataTest extends TestCase
{
    /** @return array<int, array<string, mixed>> */
    private function schema(string $path): array
    {
        preg_match_all(
            '#<script type="application/ld\+json">(.*?)</script>#s',
            $this->get($path)->assertOk()->getContent(),
            $found,
        );

        return array_map(fn ($json) => json_decode($json, true), $found[1]);
    }

    private function ofType(string $path, string $type): ?array
    {
        foreach ($this->schema($path) as $block) {
            if (($block['@type'] ?? null) === $type) {
                return $block;
            }
        }

        return null;
    }

    private function faq(array $overrides = []): Faq
    {
        return Faq::create(array_merge([
            'question' => 'What does it cost?',
            'answer' => 'Nothing to talk to us.',
            'sort_order' => 1,
            'active' => true,
        ], $overrides));
    }

    public function test_the_home_page_says_who_the_website_belongs_to(): void
    {
        $organisation = $this->ofType('/', 'Organization');

        $this->assertSame('Seniors Property Advisors', $organisation['name']);
        $this->assertSame(url('/'), $organisation['url']);
        $this->assertSame('1300 277 228', $organisation['telephone']);
        $this->assertSame('Level 14, 50 Carrington St', $organisation['address']['streetAddress']);
        $this->assertSame('Sydney', $organisation['address']['addressLocality']);
        $this->assertSame('NSW', $organisation['address']['addressRegion']);
        $this->assertSame('2000', $organisation['address']['postalCode']);

        $this->assertSame(url('/'), $this->ofType('/', 'WebSite')['url']);
    }

    /**
     * Google will not take an SVG as an organisation's logo. The site's own logo is one, so the
     * default sharing picture stands in rather than shipping something that fails validation.
     */
    public function test_the_logo_is_never_the_svg(): void
    {
        /* The site logo is an SVG, so the default sharing picture stands in. */
        $this->assertSame(url('/media/2026/08/share-card.jpg'), $this->ofType('/', 'Organization')['logo']);

        $globals = Setting::where('key', 'globals')->value('value');
        $globals['logo']['src'] = '/media/2026/08/logo.png';
        Setting::where('key', 'globals')->update(['value' => $globals]);

        /* A raster logo is picked up the moment one exists. */
        $this->assertSame(url('/media/2026/08/logo.png'), $this->ofType('/', 'Organization')['logo']);
    }

    /** An empty property claims the value is empty; a missing one says we do not know it. */
    public function test_social_addresses_are_left_out_when_there_are_none(): void
    {
        $this->assertArrayNotHasKey('sameAs', $this->ofType('/', 'Organization'));

        $site = Setting::where('key', 'site')->value('value');
        $site['social']['facebook'] = 'https://facebook.com/example';
        Setting::where('key', 'site')->update(['value' => $site]);

        $this->assertSame(['https://facebook.com/example'], $this->ofType('/', 'Organization')['sameAs']);
    }

    public function test_a_page_below_the_home_page_carries_a_trail(): void
    {
        $crumbs = $this->ofType('/how-it-works', 'BreadcrumbList')['itemListElement'];

        $this->assertSame(['Home', 'How it works'], array_column($crumbs, 'name'));
        $this->assertSame([url('/'), url('/how-it-works')], array_column($crumbs, 'item'));
        $this->assertSame([1, 2], array_column($crumbs, 'position'));
    }

    public function test_the_home_page_has_no_trail_to_itself(): void
    {
        $this->assertNull($this->ofType('/', 'BreadcrumbList'));
    }

    public function test_the_questions_on_the_page_are_the_questions_in_the_schema(): void
    {
        $this->faq(['question' => 'First?', 'answer' => 'Yes.', 'sort_order' => 1]);
        $this->faq(['question' => 'Second?', 'answer' => 'Also yes.', 'sort_order' => 2]);

        $questions = $this->ofType('/faqs', 'FAQPage')['mainEntity'];

        $this->assertSame(['First?', 'Second?'], array_column($questions, 'name'));
        $this->assertSame('Yes.', $questions[0]['acceptedAnswer']['text']);
    }

    /**
     * Describing a question a reader cannot find on the page is the way this markup gets a site
     * penalised, so anything the section filters out has to be filtered out here too.
     */
    public function test_a_question_the_reader_cannot_see_is_not_described(): void
    {
        $this->faq(['question' => 'Shown?', 'answer' => 'Yes.']);
        $this->faq(['question' => 'Switched off?', 'answer' => 'No.', 'active' => false]);
        $this->faq(['question' => 'Someone else’s page?', 'answer' => 'No.', 'page_slug' => 'how-it-works']);

        $questions = array_column($this->ofType('/faqs', 'FAQPage')['mainEntity'], 'name');

        $this->assertSame(['Shown?'], $questions);
    }

    public function test_a_limit_on_the_section_truncates_the_schema_too(): void
    {
        $this->faq(['question' => 'First?', 'sort_order' => 1]);
        $this->faq(['question' => 'Second?', 'sort_order' => 2]);

        $page = Page::where('slug', 'faqs')->firstOrFail();
        $sections = $page->published;
        $sections[0]['data']['limit'] = 1;
        $page->update(['published' => $sections]);

        $this->assertCount(1, $this->ofType('/faqs', 'FAQPage')['mainEntity']);
    }

    /** No library questions yet, so the section falls back to whatever was typed into it. */
    public function test_hand_written_questions_are_described_when_the_library_is_empty(): void
    {
        $page = Page::where('slug', 'faqs')->firstOrFail();
        $sections = $page->published;
        $sections[0]['data']['items'] = [
            ['question' => 'Typed in?', 'answer' => 'Yes.'],
            ['question' => 'Half finished?', 'answer' => ''],
        ];
        $page->update(['published' => $sections]);

        $questions = array_column($this->ofType('/faqs', 'FAQPage')['mainEntity'], 'name');

        $this->assertSame(['Typed in?'], $questions);
    }

    /** Markup describing a page a crawler has been told to forget argues with itself. */
    public function test_a_page_hidden_from_search_describes_nothing(): void
    {
        $this->faq();

        Page::where('slug', 'faqs')->update(['seo' => ['noindex' => true]]);

        $this->assertSame([], $this->schema('/faqs'));
    }

    public function test_each_block_is_its_own_script(): void
    {
        $this->assertCount(2, $this->schema('/'));
    }
}
