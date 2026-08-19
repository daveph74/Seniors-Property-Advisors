<?php

namespace Tests\Feature;

use App\Cms\SeoReport;
use App\Content\Site;
use App\Models\BlogPost;
use App\Models\Page;
use Database\Seeders\SampleContentSeeder;
use Tests\TestCase;

/**
 * The metadata every public address ships, held to the lengths a search result actually shows.
 *
 * These are content assertions, which is unusual here and deliberate: the copy was rewritten once to fit
 * inside a snippet, and without a test the next edit puts it back over the line one page at a time. A
 * description that truncates is not broken — it is simply cut off mid-sentence in the one place a reader
 * decides whether to click, and nothing in the application would ever complain.
 */
class SeoContentTest extends TestCase
{
    /** Roughly what Google renders before the ellipsis. The stored limit is 320, which is a different question. */
    private const DESCRIPTION_LIMIT = 155;

    /** A title is cut on pixel width, not characters; 61 is the point where that starts to bite. */
    private const TITLE_LIMIT = 61;

    public function test_every_published_address_says_something_of_its_own(): void
    {
        foreach (SeoReport::rows() as $row) {
            if ($row['status'] !== 'published') {
                continue;
            }

            $this->assertNotSame('', $row['description'], "{$row['url']} has no description at all");
            $this->assertFalse(
                $row['descriptionInherited'],
                "{$row['url']} is falling back to the site-wide description rather than saying anything about itself",
            );
            $this->assertNotSame('', $row['headTitle'], "{$row['url']} has no title");
        }
    }

    public function test_no_description_is_long_enough_to_be_cut_off(): void
    {
        foreach (SeoReport::rows() as $row) {
            if ($row['status'] !== 'published') {
                continue;
            }

            $this->assertLessThanOrEqual(
                self::DESCRIPTION_LIMIT,
                $row['descriptionLength'],
                "{$row['url']} would be truncated in a search result ({$row['descriptionLength']} characters)",
            );
        }
    }

    public function test_no_title_is_long_enough_to_be_cut_off(): void
    {
        foreach (SeoReport::rows() as $row) {
            if ($row['status'] !== 'published') {
                continue;
            }

            $this->assertLessThanOrEqual(
                self::TITLE_LIMIT,
                $row['titleLength'],
                "{$row['url']} has a title of {$row['titleLength']} characters, including the site name the format appends",
            );
        }
    }

    /**
     * The same sentence on two addresses tells a reader nothing about which one to open, and asks a search
     * engine to pick between them. Near-duplicates are the real risk — the old copy ended two pages with
     * "nothing to sell you" — but exact repetition is what can be asserted.
     */
    public function test_no_two_addresses_share_a_description(): void
    {
        $descriptions = [];

        foreach (SeoReport::rows() as $row) {
            if ($row['status'] !== 'published' || $row['descriptionInherited']) {
                continue;
            }

            $seen = $descriptions[$row['description']] ?? null;

            $this->assertNull($seen, "{$row['url']} repeats the description on {$seen}");

            $descriptions[$row['description']] = $row['url'];
        }
    }

    /**
     * The fallback exists for a page whose own description is ever cleared. It was `null`, so that page
     * would have shipped no description and no `og:description` at all — dormant only because every page
     * happened to have one.
     */
    public function test_the_site_carries_a_fallback_description(): void
    {
        $default = Site::seoDefaults()['description'];

        $this->assertNotEmpty($default, 'a page with its own description cleared would ship none at all');
        $this->assertLessThanOrEqual(self::DESCRIPTION_LIMIT, mb_strlen($default));
    }

    /** An article inherits nothing now, so three of them cannot share one sentence in the results. */
    public function test_every_article_has_a_description_of_its_own(): void
    {
        /* Seeded here rather than globally: `Tests\TestCase` leaves the sample articles out on
           purpose, because content in every test's seed breaks the counts other suites assert. */
        $this->seed(SampleContentSeeder::class);

        $this->assertNotEmpty(BlogPost::published()->get(), 'the seeder should have produced articles');

        foreach (BlogPost::published()->get() as $article) {
            $description = $article->seo['description'] ?? '';

            $this->assertNotSame('', $description, "{$article->slug} has no description of its own");
            $this->assertNotSame($article->summary, $description, "{$article->slug} reuses its card blurb");
            $this->assertLessThanOrEqual(self::DESCRIPTION_LIMIT, mb_strlen($description));
        }
    }

    /**
     * The seed files are the source of truth for a fresh install and the database is what serves, so both
     * were written. If they drift, a `migrate:fresh --seed` silently reverts this work.
     */
    public function test_the_seed_files_and_the_database_agree(): void
    {
        foreach (glob(resource_path('content/pages/*.json')) ?: [] as $path) {
            $document = json_decode(file_get_contents($path), true);
            $page = Page::where('slug', $document['slug'])->first();

            $this->assertNotNull($page, "{$document['slug']} is in the seed files but not the database");
            $this->assertSame(
                $document['seo']['description'] ?? null,
                $page->seo['description'] ?? null,
                "{$document['slug']}: the seed file and the database disagree about the description",
            );
        }
    }
}
