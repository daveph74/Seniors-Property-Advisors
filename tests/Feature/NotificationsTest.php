<?php

namespace Tests\Feature;

use App\Models\Enquiry;
use App\Models\Page;
use Tests\TestCase;

/**
 * The bell and the sidebar answer two different questions, and the point of these tests is that
 * they keep answering different ones: the bell falls when somebody looks, the sidebar only when
 * the work is done.
 */
class NotificationsTest extends TestCase
{
    private function enquiry(array $overrides = []): Enquiry
    {
        return Enquiry::create(array_merge([
            'name' => 'Janet Reid', 'email' => 'janet@example.com', 'consented' => true,
        ], $overrides));
    }

    private function notifications(string $url = '/cms'): array
    {
        return $this->get($url)->assertOk()->viewData('page')['props']['notifications'];
    }

    public function test_the_badge_counts_enquiries_nobody_has_opened(): void
    {
        $this->enquiry();
        $this->enquiry(['name' => 'Peter Vaughn']);
        $this->enquiry(['name' => 'Already seen', 'read_at' => now()]);

        $notifications = $this->notifications();

        $this->assertSame(2, $notifications['unread']);
        $this->assertSame(['Peter Vaughn', 'Janet Reid'], array_column($notifications['items'], 'name'));
    }

    public function test_each_listed_enquiry_links_to_itself(): void
    {
        $enquiry = $this->enquiry();

        $this->assertSame("/cms/enquiries?open={$enquiry->id}", $this->notifications()['items'][0]['href']);
        $this->get($this->notifications()['items'][0]['href'])->assertOk();
    }

    /**
     * The one that pins the whole design. Reading an enquiry silences the bell but must not touch
     * the sidebar — otherwise glancing at something would report the work as done.
     */
    public function test_reading_one_clears_the_badge_but_not_the_work(): void
    {
        $enquiry = $this->enquiry();

        $this->assertSame(1, $this->notifications()['unread']);
        $this->assertSame(1, $this->notifications()['counts']['enquiries']);

        $this->get("/cms/enquiries?open={$enquiry->id}")->assertOk();

        $this->assertSame(0, $this->notifications()['unread']);
        $this->assertSame(1, $this->notifications()['counts']['enquiries'], 'reading is not answering');

        $this->patch("/cms/enquiries/{$enquiry->id}/status", ['status' => Enquiry::DEALT_WITH]);

        $this->assertSame(0, $this->notifications()['counts']['enquiries']);
    }

    public function test_an_enquiry_in_progress_still_counts_as_outstanding(): void
    {
        $enquiry = $this->enquiry(['read_at' => now()]);

        $this->patch("/cms/enquiries/{$enquiry->id}/status", ['status' => Enquiry::IN_PROGRESS]);

        $this->assertSame(1, $this->notifications()['counts']['enquiries']);
        $this->assertSame(0, $this->notifications()['unread']);
    }

    public function test_the_panel_lists_at_most_eight(): void
    {
        foreach (range(1, 11) as $n) {
            $this->enquiry(['name' => "Sender {$n}"]);
        }

        $notifications = $this->notifications();

        $this->assertSame(11, $notifications['unread'], 'the badge counts them all');
        $this->assertCount(8, $notifications['items'], 'the list is bounded');
    }

    public function test_the_sidebar_counts_pages_holding_an_unpublished_draft(): void
    {
        Page::where('status', 'published')->firstOrFail()
            ->update(['draft' => [['type' => 'hero', 'id' => 'a', 'data' => []]]]);

        $this->assertSame(1, $this->notifications()['counts']['pages']);
    }

    public function test_nothing_waiting_is_all_zeroes(): void
    {
        Enquiry::query()->delete();
        Page::query()->update(['draft' => null]);

        $notifications = $this->notifications();

        $this->assertSame(0, $notifications['unread']);
        $this->assertSame([], $notifications['items']);
        $this->assertSame(['enquiries' => 0, 'pages' => 0], $notifications['counts']);
    }

    /* The public site shares this middleware, and these counts per page view is a bill nobody asked for. */
    public function test_a_public_page_carries_no_counts(): void
    {
        $this->assertNull($this->get('/')->assertOk()->viewData('page')['props']['notifications']);
    }
}
