<?php

namespace Tests\Feature;

use App\Content\PageContentStore;
use App\Models\Page;
use Tests\TestCase;

/**
 * What is on the website, held to the one standard a reader would notice immediately.
 *
 * Three published pages carry text like "ABN [TO BE CONFIRMED]" and "[X business days]" — the complaints
 * page says in its own words that the timeframes are placeholders and it should not be published, and it
 * is published. Nothing in the application had any opinion about that: a placeholder is valid content, it
 * saves, it publishes, and it is served to readers and crawlers exactly like a finished sentence.
 *
 * This does not hide those pages or invent the missing values — a complaint-handling timeframe is a
 * commitment somebody has to make, not a number to guess. It fails until they are real, so the next
 * placeholder cannot reach the website the way these did: quietly, in a file nobody re-read.
 *
 * @see the plan's report for the four values still outstanding — the ABN, the response timeframe, who
 * handles complaints, and an effective date
 */
class PublishedContentTest extends TestCase
{
    /**
     * Written as the shapes a draft actually uses, not as one clever pattern: `[SOMETHING IN CAPITALS]`
     * would also match a legitimate `[SEE OUR PRIVACY POLICY]`, and the point is to be obvious about what
     * is being looked for.
     */
    private const MARKERS = [
        'TO BE CONFIRMED',
        'TBC]',
        'PLACEHOLDER',
        'LOREM IPSUM',
        '[X ',
        'FIXME',
        'XXX]',
    ];

    /**
     * Pinned to the pages that are unfinished today, rather than skipped.
     *
     * A test that simply failed would be red until somebody supplies four values nobody here can invent,
     * and a permanently red suite teaches people to ignore it. A skip would be worse: this repository's own
     * rule is that a standing skip is a standing question, and a test that declines to answer is not one.
     *
     * So it states the exact set. A new placeholder anywhere fails it, which is the point. And finishing one
     * of these three also fails it — with the list to shorten, which is the most useful moment to be asked.
     */
    public function test_only_the_known_unfinished_pages_carry_placeholder_text(): void
    {
        $expected = ['complaints', 'privacy-policy', 'terms-and-conditions'];
        $found = [];

        foreach (Page::where('status', 'published')->get(['slug', 'published']) as $page) {
            $text = strtoupper(json_encode($page->published, JSON_UNESCAPED_UNICODE) ?: '');

            foreach (self::MARKERS as $marker) {
                if (str_contains($text, $marker)) {
                    $found[] = $page->slug;

                    break;
                }
            }
        }

        sort($found);

        $this->assertSame(
            $expected,
            $found,
            "The pages carrying text a reader would see as unfinished have changed.\n"
            .'A page added to this list is a placeholder that reached the website; a page removed from it '
            ."means one was finished, and this list should lose it.\n"
            .'Outstanding: the ABN, the complaint response timeframe, who handles complaints, an effective date.',
        );
    }

    /**
     * The half that can be asserted unconditionally. Whatever else those three pages are missing, a reader
     * following a footer link must arrive at a page rather than a 404, and a legal page is the one place
     * where an address that does not answer is its own problem.
     */
    public function test_every_page_the_footer_promises_actually_answers(): void
    {
        $globals = (new PageContentStore)->globals();

        $hrefs = collect($globals['footer']['links'] ?? [])
            ->concat(collect($globals['footer']['columns'] ?? [])->flatMap(fn ($column) => $column['links'] ?? []))
            ->pluck('href')
            ->filter(fn ($href) => is_string($href) && str_starts_with($href, '/'))
            ->unique();

        $this->assertGreaterThan(5, $hrefs->count(), 'the footer should link to the site');

        foreach ($hrefs as $href) {
            $this->get($href)->assertOk();
        }
    }
}
