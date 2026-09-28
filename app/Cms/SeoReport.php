<?php

namespace App\Cms;

use App\Content\Seo;
use App\Content\Site;
use App\Models\BlogPost;
use App\Models\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Every address a crawler could reach, and what it would find there.
 *
 * This is a view over data that already exists, not a second copy of it. Two rules keep it honest:
 *
 * **What is reported is what is rendered.** Each row goes through `Seo::head()` — the same function
 * `app.blade.php` prints from — with the row's own public address supplied. That address is the only
 * reason this works: `head()` resolves an absent canonical to `$seo['url']` and never reads the
 * request, so a report built on `/cms/seo` shows the page's canonical rather than the admin one. Pass
 * the URL and the values are the delivered values, character for character.
 *
 * **Whether a URL is in the sitemap is asked of the sitemap.** `sitemapUrls()` is what
 * `SitemapController` serves, so membership is a fact rather than a restatement of the rule, and
 * `reasonsFor()` only ever explains a "no" the sitemap has already given. A shared predicate would
 * have left two call sites free to drift; one produced list cannot.
 */
final class SeoReport
{
    /**
     * The stored limit is 320 (`SavePageDetailsRequest`), and a search result shows about 155. Both
     * numbers matter: the first is what may be saved, the second is what anybody will read, so a
     * description can be perfectly valid and still be reported as too long.
     */
    public const DESCRIPTION_IDEAL = 155;

    public const DESCRIPTION_SHORT = 70;

    public const TITLE_IDEAL = 60;

    /**
     * Assembling every row costs two queries and no per-row work, so the ceiling is about the size
     * of the response rather than the cost of building it. Past this the screen says it is
     * truncated — a report that quietly gets slower every month is worse than one that admits it.
     */
    public const CEILING = 2000;

    public const SHOW = ['all', 'no-description', 'hidden', 'not-in-sitemap', 'pages', 'articles'];

    /**
     * The addresses worth crawling, and when each last changed.
     *
     * `status` carries most of the answer — draft and archived are excluded by it, and soft deletes
     * drop a trashed article — leaving only a page the editor has hidden from search.
     *
     * The two models answer "when did this change" differently, and the difference is the point. A
     * page has a draft, so its `updated_at` moves when somebody saves work nobody can see yet;
     * `published_at` is re-stamped on every publish, which is the only moment a reader's copy
     * changes. An article has no draft — editing a published one changes it live — so `updated_at`
     * is the honest answer there. Reporting a draft save as a change asks every crawler to re-fetch
     * a page that did not move, which is the one thing lastmod exists to avoid.
     *
     * A page with no `published_at` therefore gets **no lastmod at all** rather than falling back to
     * `updated_at`. Several seeded pages are in exactly that state, and the fallback was written
     * first: it put the draft-save time back for precisely the pages the rule was meant to protect.
     * An absent lastmod says "I do not know", which is both true and valid; a wrong one is neither.
     *
     * @return Collection<int, array{loc: string, lastmod: string|null}>
     */
    public static function sitemapUrls(): Collection
    {
        $pages = Page::where('status', 'published')
            ->get(['url', 'seo', 'published_at', 'updated_at'])
            ->reject(fn (Page $page) => $page->seo['noindex'] ?? false)
            ->map(fn (Page $page) => [
                'loc' => url($page->url),
                'lastmod' => $page->published_at?->toAtomString(),
            ]);

        $articles = BlogPost::published()
            ->get(['slug', 'seo', 'published_at', 'updated_at'])
            ->reject(fn (BlogPost $post) => $post->seo['noindex'] ?? false)
            ->map(fn (BlogPost $post) => [
                'loc' => url($post->url()),
                'lastmod' => ($post->updated_at ?? $post->published_at)?->toAtomString(),
            ]);

        return $pages->concat($articles)->values();
    }

    /** @return array<int, array<string, mixed>> */
    public static function rows(): array
    {
        $defaults = Site::seoDefaults();
        $advertised = self::sitemapUrls()->pluck('loc')->all();

        $pages = Page::query()
            ->get(['id', 'cms_id', 'url', 'title', 'status', 'seo', 'published_at', 'updated_at'])
            ->map(fn (Page $page) => self::pageRow($page, $defaults, $advertised));

        /* Trashed articles included on purpose: an address a search engine still holds is exactly
           what somebody comes to this screen to explain. They carry no edit link — restoring one
           is the Deleted content screen's job, behind a different ability. */
        $articles = BlogPost::withTrashed()
            ->get(['id', 'slug', 'title', 'status', 'seo', 'featured_image', 'published_at', 'updated_at', 'deleted_at'])
            ->map(fn (BlogPost $post) => self::articleRow($post, $defaults, $advertised));

        return $pages->concat($articles)
            ->sortByDesc('changedAt')
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $advertised
     * @return array<string, mixed>
     */
    private static function pageRow(Page $page, array $defaults, array $advertised): array
    {
        $full = url($page->url);
        $seo = $page->seo ?? [];
        $head = self::head($seo, $full, $page->title, 'website', $defaults);

        return self::row([
            'key' => 'page-'.$page->id,
            'kind' => 'page',
            'title' => $page->title,
            'url' => $page->url,
            'fullUrl' => $full,
            'status' => $page->status,
            'deletedAt' => null,
            'image' => $seo['image'] ?? $defaults['image'],
            'imageInherited' => trim((string) ($seo['image'] ?? '')) === '',
            'changedAt' => ($page->published_at ?? $page->updated_at)?->toIso8601String(),
            /* cms_id, not id: CmsPageController::edit resolves through findByCmsId, so a link of
               the right shape built from the primary key answers 404. */
            'editUrl' => '/cms/pages/'.$page->cms_id.'/edit',
            'patchId' => $page->cms_id,
        ], $seo, $head, $advertised, $defaults);
    }

    /**
     * @param  array<int, string>  $advertised
     * @return array<string, mixed>
     */
    private static function articleRow(BlogPost $post, array $defaults, array $advertised): array
    {
        $full = url($post->url());
        $seo = $post->seo ?? [];
        $head = self::head($seo, $full, $post->title, 'article', $defaults);

        return self::row([
            'key' => 'article-'.$post->id,
            'kind' => 'article',
            'title' => $post->title,
            'url' => $post->url(),
            'fullUrl' => $full,
            'status' => $post->status,
            'deletedAt' => $post->deleted_at?->toIso8601String(),
            'image' => $seo['image'] ?? $post->featured_image ?? $defaults['image'],
            'imageInherited' => trim((string) ($seo['image'] ?? '')) === ''
                && trim((string) $post->featured_image) === '',
            'changedAt' => ($post->updated_at ?? $post->published_at)?->toIso8601String(),
            'editUrl' => $post->deleted_at === null ? '/cms/blog/'.$post->id.'/edit' : null,
            /* An article is genuinely keyed on its primary key; only pages carry a separate
               builder id. */
            'patchId' => $post->id,
        ], $seo, $head, $advertised, $defaults);
    }

    /**
     * `array_merge`, not `+`: `seo` is a free-form JSON column, and a stored `url` key would
     * otherwise decide the canonical of every row it appeared on. `noindex` is deliberately left
     * exactly as stored — the preview endpoints force it on, and borrowing that call here would
     * report every address as hidden.
     *
     * @return array<string, mixed>
     */
    private static function head(array $seo, string $url, string $title, string $type, array $defaults): array
    {
        return Seo::head(array_merge($seo, ['url' => $url]), $title, $type, $defaults);
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<int, string>  $advertised
     * @return array<string, mixed>
     */
    private static function row(array $base, array $seo, array $head, array $advertised, array $defaults): array
    {
        $headTitle = (string) ($head['title'] ?? '');
        $description = (string) ($head['description'] ?? '');
        $canonical = (string) ($head['canonical'] ?? '');
        $noindex = (bool) ($seo['noindex'] ?? false);

        $inSitemap = in_array($base['fullUrl'], $advertised, true);

        $row = $base + [
            'headTitle' => $headTitle,
            'titleLength' => mb_strlen($headTitle),
            'titleInherited' => trim((string) ($seo['title'] ?? '')) === '',
            'description' => $description,
            'descriptionLength' => mb_strlen($description),
            /* The site default filling in is not the same as the page having one. Twelve URLs
               sharing one description is a finding; reporting them all as complete hides it. */
            'descriptionInherited' => trim((string) ($seo['description'] ?? '')) === ''
                && trim((string) ($defaults['description'] ?? '')) !== '',
            'canonical' => $canonical,
            'canonicalOverridden' => trim((string) ($seo['canonical'] ?? '')) !== '',
            'noindex' => $noindex,
            'inSitemap' => $inSitemap,
            'sitemapReasons' => $inSitemap ? [] : self::reasonsFor($base['status'], $noindex, $base['deletedAt']),
        ];

        return $row + ['issues' => self::issues($row)];
    }

    /**
     * Why the sitemap left an address out — an explanation of its answer, never a second opinion.
     *
     * The fallback is the load-bearing part. A rule added to `sitemapUrls()` that this list has
     * never heard of would otherwise produce a row marked absent with nothing said about it, which
     * reads as a bug in this screen. A test asserts the fallback never fires today, so the day it
     * does is the day somebody has to come back here.
     *
     * @return array<int, string>
     */
    private static function reasonsFor(string $status, bool $noindex, ?string $deletedAt): array
    {
        $reasons = [];

        if ($deletedAt !== null) {
            $reasons[] = 'deleted';
        }

        if ($status !== 'published') {
            $reasons[] = $status;
        }

        if ($noindex) {
            $reasons[] = 'hidden';
        }

        return $reasons === [] ? ['excluded'] : $reasons;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<int, string>
     */
    private static function issues(array $row): array
    {
        $issues = [];

        if ($row['description'] === '') {
            $issues[] = 'no-description';
        } elseif ($row['descriptionLength'] > self::DESCRIPTION_IDEAL) {
            $issues[] = 'description-long';
        } elseif ($row['descriptionLength'] < self::DESCRIPTION_SHORT) {
            $issues[] = 'description-short';
        }

        if ($row['descriptionInherited'] && $row['description'] !== '') {
            $issues[] = 'description-inherited';
        }

        if ($row['headTitle'] === '') {
            $issues[] = 'no-title';
        } elseif ($row['titleLength'] > self::TITLE_IDEAL) {
            $issues[] = 'title-long';
        }

        if ($row['image'] === null || trim((string) $row['image']) === '') {
            $issues[] = 'no-image';
        }

        if ($row['canonicalOverridden'] && rtrim($row['canonical'], '/') !== rtrim($row['fullUrl'], '/')) {
            $issues[] = 'canonical-elsewhere';
        }

        if ($row['noindex']) {
            $issues[] = 'hidden';
        }

        if (! $row['inSitemap']) {
            $issues[] = 'not-in-sitemap';
        }

        return $issues;
    }

    /** An allowlist, echoed back so the control can light the tab that is actually in force. */
    public static function show(mixed $value): string
    {
        return in_array($value, self::SHOW, true) ? (string) $value : 'all';
    }

    /**
     * Filtered in PHP rather than in SQL, and not through `Like`.
     *
     * Half of what is reported does not exist in a column: the title after the site format, a
     * description that arrived from the site default, membership of the sitemap. `Like::any()` takes
     * a query builder and cannot see any of them, so pushing the search box down to SQL would find
     * fewer rows than the eye can see on the screen it is filtering.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public static function filtered(array $rows, string $show, string $term): array
    {
        $term = Str::lower(trim(mb_substr($term, 0, 120)));

        $matching = array_filter($rows, function (array $row) use ($show, $term) {
            $keep = match ($show) {
                'no-description' => in_array('no-description', $row['issues'], true),
                'hidden' => $row['noindex'],
                'not-in-sitemap' => ! $row['inSitemap'],
                'pages' => $row['kind'] === 'page',
                'articles' => $row['kind'] === 'article',
                default => true,
            };

            if (! $keep || $term === '') {
                return $keep;
            }

            foreach (['url', 'title', 'headTitle', 'description'] as $field) {
                if (Str::contains(Str::lower((string) $row[$field]), $term)) {
                    return true;
                }
            }

            return false;
        });

        return array_values($matching);
    }

    /**
     * How many rows each tab would show. Read from the whole set rather than the filtered one, so a
     * count never describes the tab you are already standing on.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, int>
     */
    public static function counts(array $rows): array
    {
        $counts = [];

        foreach (self::SHOW as $show) {
            $counts[$show] = count(self::filtered($rows, $show, ''));
        }

        return $counts;
    }
}
