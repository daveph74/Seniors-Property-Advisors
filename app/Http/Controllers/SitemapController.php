<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\Page;
use Illuminate\Http\Response;

/**
 * The list of addresses worth crawling.
 *
 * `status` already carries the whole answer for both models — draft and archived are excluded by
 * it, and BlogPost's soft deletes drop trashed articles — so the only thing left to filter is a
 * page the editor has hidden from search. A sitemap that advertises a noindexed page asks a
 * crawler to fetch something it is then told to forget.
 *
 * Deliberately uncached. This is two queries over a few dozen rows, where a cache would have to be
 * invalidated on publish, unpublish, archive, restore and every blog status change — five ways to
 * serve a stale sitemap in exchange for nothing measurable.
 */
class SitemapController extends Controller
{
    public function index(): Response
    {
        $pages = Page::where('status', 'published')
            ->get(['url', 'seo', 'published_at', 'updated_at'])
            ->reject(fn (Page $page) => $page->seo['noindex'] ?? false)
            ->map(fn (Page $page) => [
                'loc' => url($page->url),
                'lastmod' => ($page->updated_at ?? $page->published_at)?->toAtomString(),
            ]);

        $articles = BlogPost::published()
            ->get(['slug', 'seo', 'published_at', 'updated_at'])
            ->reject(fn (BlogPost $post) => $post->seo['noindex'] ?? false)
            ->map(fn (BlogPost $post) => [
                'loc' => url($post->url()),
                'lastmod' => ($post->updated_at ?? $post->published_at)?->toAtomString(),
            ]);

        return response()
            ->view('sitemap', ['urls' => $pages->concat($articles)->values()])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /**
     * robots.txt as a route rather than a file in public/, so the sitemap address is built from
     * the current host. Hardcoding it means one file that is right in production and wrong
     * everywhere else, and wrong from the day the domain changes.
     */
    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /cms/',
            'Disallow: /login',
            '',
            'Sitemap: '.url('/sitemap.xml'),
        ];

        return response(implode("\n", $lines)."\n")
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
