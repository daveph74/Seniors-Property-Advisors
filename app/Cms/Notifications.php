<?php

namespace App\Cms;

use App\Models\Enquiry;
use App\Models\Page;

/**
 * What the header's bell and the sidebar's counts report — two different questions, kept apart
 * on purpose.
 *
 * The **bell** counts enquiries nobody has opened yet. It is a "new since anyone last looked", so
 * opening one clears it, and it can honestly reach zero.
 *
 * The **sidebar** counts work still outstanding: enquiries not yet dealt with, and published pages
 * holding an edit readers cannot see. Nothing marks those read — they fall when the work is done,
 * which is why they are not in the bell. A badge that can be cleared by glancing at something must
 * never be the one saying how much is left to do; that was the objection to giving the bell a read
 * state at all, and splitting the two is the answer to it.
 *
 * Both are derived. There is still no notifications table: `read_at` lives on the enquiry, so an
 * enquiry read by one person is read for the inbox.
 */
class Notifications
{
    /** How many unread the panel lists. The badge counts them all; this only bounds the list. */
    private const PANEL = 8;

    public static function for(): array
    {
        return [
            'unread' => Enquiry::unread()->count(),
            'items' => Enquiry::unread()
                ->latest('created_at')
                ->latest('id')
                ->limit(self::PANEL)
                ->get()
                ->map(fn (Enquiry $enquiry) => [
                    'id' => $enquiry->id,
                    'name' => $enquiry->name,
                    'at' => $enquiry->created_at?->toIso8601String(),
                    'href' => "/cms/enquiries?open={$enquiry->id}",
                ])
                ->all(),
            'counts' => [
                'enquiries' => Enquiry::outstanding()->count(),
                'pages' => self::pagesWithUnpublishedChanges(),
            ],
        ];
    }

    /**
     * The same rule `PageContentStore` uses for the "Unpublished changes" filter on /cms/pages, so
     * the sidebar and that screen cannot disagree.
     */
    private static function pagesWithUnpublishedChanges(): int
    {
        return Page::query()
            ->where('status', 'published')
            ->whereNotNull('draft')
            ->count();
    }
}
