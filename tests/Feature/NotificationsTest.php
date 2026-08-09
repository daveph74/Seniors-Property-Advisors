<?php

namespace Tests\Feature;

use App\Models\Enquiry;
use App\Models\Page;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    private function notifications(string $url = '/cms'): array
    {
        return $this->get($url)->assertOk()->viewData('page')['props']['notifications'];
    }

    public function test_it_counts_enquiries_nobody_has_handled(): void
    {
        Enquiry::create(['name' => 'Janet Reid', 'email' => 'janet@example.com', 'consented' => true]);
        Enquiry::create([
            'name' => 'Peter Vaughn', 'email' => 'peter@example.com', 'consented' => true,
            'handled_at' => now(),
        ]);

        $notifications = $this->notifications();
        $enquiries = collect($notifications['items'])->firstWhere('key', 'enquiries');

        $this->assertSame(1, $enquiries['count']);
        $this->assertSame('1 enquiry to answer', $enquiries['label']);
        $this->assertSame('/cms/enquiries', $enquiries['href']);
    }

    public function test_it_counts_published_pages_carrying_an_unpublished_draft(): void
    {
        $page = Page::where('status', 'published')->firstOrFail();
        $page->update(['draft' => [['type' => 'hero', 'id' => 'a', 'data' => []]]]);

        $pages = collect($this->notifications()['items'])->firstWhere('key', 'pages');

        $this->assertSame(1, $pages['count']);
        $this->assertSame('1 page has unpublished changes', $pages['label']);
    }

    /* A page that has never been published is not a change waiting to go out. */
    public function test_a_page_that_was_never_published_is_not_counted(): void
    {
        Page::create([
            'cms_id' => 900, 'slug' => 'brand-new', 'url' => '/brand-new', 'title' => 'Brand new',
            'status' => 'draft', 'draft' => [['type' => 'hero', 'id' => 'a', 'data' => []]],
        ]);

        $this->assertSame(0, collect($this->notifications()['items'])->firstWhere('key', 'pages')['count']);
    }

    public function test_the_total_adds_both_up(): void
    {
        Enquiry::create(['name' => 'Janet Reid', 'email' => 'janet@example.com', 'consented' => true]);
        Page::where('status', 'published')->firstOrFail()->update(['draft' => [['type' => 'hero', 'id' => 'a', 'data' => []]]]);

        $this->assertSame(2, $this->notifications()['total']);
    }

    public function test_nothing_waiting_is_a_total_of_zero(): void
    {
        Enquiry::query()->delete();
        Page::query()->update(['draft' => null]);

        $this->assertSame(0, $this->notifications()['total']);
    }

    /* The public site shares this middleware, and two counts per page view is a bill nobody asked for. */
    public function test_a_public_page_carries_no_counts(): void
    {
        $this->assertNull($this->get('/')->assertOk()->viewData('page')['props']['notifications']);
    }
}
