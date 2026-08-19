<?php

namespace Tests\Feature;

use App\Cms\SeoReport;
use App\Content\Site;
use App\Models\BlogPost;
use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use Tests\TestCase;

/**
 * The SEO overview reports what a crawler receives, and every number on it has to be derivable from
 * the public site rather than asserted alongside it.
 *
 * That is the whole risk this screen carries. A report that quietly disagrees with the sitemap, or
 * that shows an admin URL as a page's canonical, is worse than no report: somebody would act on it.
 */
class SeoReportTest extends TestCase
{
    private function article(array $overrides = []): BlogPost
    {
        return BlogPost::create(array_merge([
            'slug' => 'an-article',
            'title' => 'An article',
            'body' => '<p>Words.</p>',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ], $overrides));
    }

    /** @return array<int, array<string, mixed>> */
    private function rows(): array
    {
        return SeoReport::rows();
    }

    private function rowFor(string $url): array
    {
        $row = collect($this->rows())->firstWhere('url', $url);

        $this->assertNotNull($row, "expected a row for {$url}");

        return $row;
    }

    public function test_it_lists_every_page_and_article_once(): void
    {
        $this->article();

        $urls = array_column($this->rows(), 'url');

        $this->assertSame(array_unique($urls), $urls, 'an address is reported twice');
        $this->assertContains('/how-it-works', $urls);
        $this->assertContains('/blog/an-article', $urls);
        $this->assertCount(Page::count() + BlogPost::withTrashed()->count(), $urls);
    }

    /**
     * The load-bearing test.
     *
     * The screen's whole claim is that its sitemap column *is* the sitemap, so this walks every row
     * and holds it against what `/sitemap.xml` actually served. Hardcoding a handful of slugs would
     * pass while a seventh rule drifted apart, so it loops.
     */
    public function test_the_reason_an_address_is_missing_from_the_sitemap_agrees_with_the_sitemap(): void
    {
        Page::where('slug', 'faqs')->update(['seo' => ['noindex' => true]]);
        Page::where('slug', 'compare-agents')->update(['status' => 'draft']);
        $this->article(['slug' => 'hidden-article', 'seo' => ['noindex' => true]]);
        $this->article(['slug' => 'draft-article', 'status' => 'draft']);
        $this->article(['slug' => 'gone-article'])->delete();

        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

        $absent = 0;

        foreach ($this->rows() as $row) {
            $advertised = str_contains($xml, '<loc>'.$row['fullUrl'].'</loc>');

            $this->assertSame(
                $advertised,
                $row['inSitemap'],
                "the report and the sitemap disagree about {$row['url']}",
            );

            if (! $row['inSitemap']) {
                $absent++;
                $this->assertNotEmpty($row['sitemapReasons'], "{$row['url']} is absent with no reason given");
            }
        }

        $this->assertGreaterThanOrEqual(5, $absent, 'the fixtures should have produced absent rows');
    }

    /**
     * The fallback reason exists so a sitemap rule this screen has never heard of still produces an
     * honest row rather than a blank one. It firing today would mean the two have already drifted.
     */
    public function test_no_absent_address_falls_back_to_an_unexplained_reason(): void
    {
        Page::where('slug', 'faqs')->update(['status' => 'archived']);
        $this->article(['status' => 'draft']);

        foreach ($this->rows() as $row) {
            $this->assertNotContains('excluded', $row['sitemapReasons'], "{$row['url']} could not be explained");
        }
    }

    /**
     * `Seo::head()` falls back to the URL it is handed, and the report is built on `/cms/seo`. Hand
     * it the wrong one and every row would claim the admin screen is its canonical address — which
     * is exactly the sort of report somebody would act on before noticing.
     */
    public function test_the_reported_canonical_is_the_public_address_not_the_admin_one(): void
    {
        $response = $this->get('/cms/seo')->assertOk();

        $row = collect($response->viewData('page')['props']['rows'])->firstWhere('url', '/how-it-works');

        $this->assertSame(url('/how-it-works'), $row['canonical']);
        $this->assertStringNotContainsString('/cms/', $row['canonical']);

        /* And it is the value the page itself delivers, which is the property that matters. */
        $this->assertStringContainsString(
            '<link inertia rel="canonical" href="'.$row['canonical'].'" />',
            $this->get('/how-it-works')->assertOk()->getContent(),
        );
    }

    public function test_an_overridden_canonical_is_flagged_as_pointing_elsewhere(): void
    {
        Page::where('slug', 'faqs')->update(['seo' => ['canonical' => 'https://example.com/other']]);

        $row = $this->rowFor('/faqs');

        $this->assertSame('https://example.com/other', $row['canonical']);
        $this->assertTrue($row['canonicalOverridden']);
        $this->assertContains('canonical-elsewhere', $row['issues']);
    }

    /**
     * A canonical that merely restates the page's own address is not a finding. Without the trailing
     * slash being ignored it would be reported as pointing elsewhere, which teaches somebody to
     * distrust the column.
     */
    public function test_a_canonical_naming_the_same_address_is_not_flagged(): void
    {
        Page::where('slug', 'faqs')->update(['seo' => ['canonical' => url('/faqs').'/']]);

        $this->assertNotContains('canonical-elsewhere', $this->rowFor('/faqs')['issues']);
    }

    /**
     * The site default filling in is not the same as an address having its own description. Twelve
     * addresses sharing one sentence is a finding; reporting them all as complete hides it.
     */
    public function test_an_address_inheriting_the_site_description_is_marked_rather_than_called_complete(): void
    {
        Setting::updateOrCreate(
            ['key' => Site::KEY],
            ['value' => array_replace(Site::all(), ['seo' => ['description' => 'The site-wide one.']])],
        );

        Page::where('slug', 'faqs')->update(['seo' => []]);

        $row = $this->rowFor('/faqs');

        $this->assertSame('The site-wide one.', $row['description']);
        $this->assertTrue($row['descriptionInherited']);
        $this->assertContains('description-inherited', $row['issues']);
        $this->assertNotContains('no-description', $row['issues']);
    }

    /**
     * 320 characters is what may be stored and roughly 155 is what a search result shows, so a
     * perfectly valid description is still worth reporting.
     */
    public function test_a_description_at_the_stored_limit_is_still_too_long_for_a_search_result(): void
    {
        Page::where('slug', 'faqs')->update(['seo' => ['description' => str_repeat('a', 320)]]);

        $this->assertContains('description-long', $this->rowFor('/faqs')['issues']);
    }

    public function test_the_title_reported_is_the_one_the_site_format_produces(): void
    {
        Setting::updateOrCreate(
            ['key' => Site::KEY],
            ['value' => array_replace(Site::all(), [
                'name' => 'Seniors Property Advisors',
                'seo' => ['titleFormat' => '{title} — {site}'],
            ])],
        );

        /* This page carries its own search title, so the reported one is that rather than the
           page's name — which is the precedence `Seo::head()` applies and the report must not
           reinvent. `titleInherited` is what says which of the two won. */
        $row = $this->rowFor('/how-it-works');

        $this->assertFalse($row['titleInherited']);
        $this->assertStringEndsWith(' — Seniors Property Advisors', $row['headTitle']);

        Page::where('slug', 'faqs')->update(['seo' => []]);
        $plain = $this->rowFor('/faqs');

        $this->assertTrue($plain['titleInherited']);
        $this->assertSame($plain['title'].' — Seniors Property Advisors', $plain['headTitle']);

        /* The home page already carries the name, so appending would deliver "… Advisors — Seniors
           Property Advisors" — the report has to show what is really sent, doubling included. */
        $home = $this->rowFor('/');
        $this->assertStringNotContainsString('Advisors — Seniors', $home['headTitle']);
    }

    /**
     * `CmsPageController::edit` resolves through `findByCmsId`, so a link built from the primary key
     * has the right shape and answers 404. Following it is the only assertion that catches that; a
     * pattern match on the address passes happily while the link is broken.
     */
    public function test_a_pages_link_reaches_its_builder(): void
    {
        $row = $this->rowFor('/how-it-works');

        $this->get($row['editUrl'])->assertOk();
    }

    public function test_a_deleted_article_is_reported_without_a_link_to_an_editor(): void
    {
        $this->article(['slug' => 'gone-article'])->delete();

        $row = $this->rowFor('/blog/gone-article');

        $this->assertNotNull($row['deletedAt']);
        $this->assertNull($row['editUrl'], 'a deleted article must not offer an editor');
        $this->assertContains('deleted', $row['sitemapReasons']);
    }

    /**
     * An un-allowlisted filter used to fall through to "everything" while the control showed nothing
     * chosen — harmless until a strip of buttons renders it as none of them lit, which reads as a
     * broken screen rather than a bad address.
     */
    public function test_an_unknown_filter_comes_back_as_everything_rather_than_nothing(): void
    {
        $this->get('/cms/seo?show=nonsense')->assertOk()->assertInertia(
            fn ($page) => $page
                ->where('filters.show', 'all')
                ->where('pagination.total', count($this->rows())),
        );
    }

    public function test_each_filter_narrows_to_what_it_names(): void
    {
        Page::where('slug', 'faqs')->update(['seo' => ['noindex' => true]]);
        $this->article();

        foreach (['pages', 'articles', 'hidden', 'not-in-sitemap', 'no-description'] as $show) {
            $rows = $this->get('/cms/seo?show='.$show)->assertOk()->viewData('page')['props']['rows'];

            $this->assertNotEmpty($rows, "the {$show} filter matched nothing");

            foreach ($rows as $row) {
                match ($show) {
                    'pages' => $this->assertSame('page', $row['kind']),
                    'articles' => $this->assertSame('article', $row['kind']),
                    'hidden' => $this->assertTrue($row['noindex']),
                    'not-in-sitemap' => $this->assertFalse($row['inSitemap']),
                    'no-description' => $this->assertContains('no-description', $row['issues']),
                };
            }
        }
    }

    public function test_it_searches_an_address_and_a_title(): void
    {
        $rows = $this->get('/cms/seo?q=how-it-works')->assertOk()->viewData('page')['props']['rows'];

        $this->assertCount(1, $rows);
        $this->assertSame('/how-it-works', $rows[0]['url']);

        $byTitle = $this->get('/cms/seo?q=How it works')->assertOk()->viewData('page')['props']['rows'];
        $this->assertSame('/how-it-works', $byTitle[0]['url']);
    }

    public function test_the_size_selector_cannot_ask_for_the_whole_table(): void
    {
        $this->get('/cms/seo?per_page=1000000')->assertOk()->assertInertia(
            fn ($page) => $page->where('pagination.perPage', 25),
        );
    }

    public function test_a_client_administrator_may_see_it_and_a_visitor_may_not(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::CLIENT_ADMIN, 'is_active' => true]));
        $this->get('/cms/seo')->assertOk();

        auth()->logout();
        $this->get('/cms/seo')->assertRedirect('/login');
    }
}
