<?php

namespace App\Http\Controllers;

use App\Cms\SeoReport;
use Illuminate\Http\Response;

/**
 * The list of addresses worth crawling.
 *
 * The list itself is `SeoReport::sitemapUrls()`, which is also what `/cms/seo` and the public
 * sitemap page read. It lives there rather than here because a report explaining why an address is
 * absent has to be reading the very list it is explaining — a second copy of the rule, however
 * carefully written, is a screen that eventually contradicts the file a crawler fetched.
 *
 * Deliberately uncached. This is two queries over a few dozen rows, where a cache would have to be
 * invalidated on publish, unpublish, archive, restore and every blog status change — five ways to
 * serve a stale sitemap in exchange for nothing measurable.
 */
class SitemapController extends Controller
{
    public function index(): Response
    {
        return response()
            ->view('sitemap', ['urls' => SeoReport::sitemapUrls()])
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
            /* The blog's load-more endpoint: article content with no page around it, which is a
               duplicate of the listing to anything that indexes it. */
            'Disallow: /blog/articles',
            '',
            'Sitemap: '.url('/sitemap.xml'),
        ];

        return response(implode("\n", $lines)."\n")
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
