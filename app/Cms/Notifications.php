<?php

namespace App\Cms;

use App\Models\Enquiry;
use App\Models\Page;

/**
 * What the bell in the header counts.
 *
 * Two things, both derived rather than stored: enquiries nobody has marked handled, and pages
 * carrying an edit that readers cannot see yet. Neither needs a notifications table, and neither
 * can drift out of step with the screen it links to — the unpublished-changes rule is the same
 * one `PageContentStore` uses for the "Unpublished changes" filter on /cms/pages.
 *
 * There is deliberately no read or dismissed state. A count that can be cleared without doing
 * the work invites clearing it, and both of these stop counting the moment the work is done.
 */
class Notifications
{
    public static function for(): array
    {
        $enquiries = Enquiry::query()->whereNull('handled_at')->count();
        $pages = self::pagesWithUnpublishedChanges();

        return [
            'items' => [
                [
                    'key' => 'enquiries',
                    'count' => $enquiries,
                    'label' => $enquiries === 1 ? '1 enquiry to answer' : "{$enquiries} enquiries to answer",
                    'href' => '/cms/enquiries',
                ],
                [
                    'key' => 'pages',
                    'count' => $pages,
                    'label' => $pages === 1 ? '1 page has unpublished changes' : "{$pages} pages have unpublished changes",
                    'href' => '/cms/pages',
                ],
            ],
            'total' => $enquiries + $pages,
        ];
    }

    private static function pagesWithUnpublishedChanges(): int
    {
        return Page::query()
            ->where('status', 'published')
            ->whereNotNull('draft')
            ->count();
    }
}
