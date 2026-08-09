<?php

namespace Tests\Feature;

use App\Models\Enquiry;
use Tests\TestCase;

/**
 * The screen that reads what the contact form collects. The form has worked all along; nothing
 * showed the result, so an enquiry was a request for help that landed where nobody would find it.
 */
class CmsEnquiryTest extends TestCase
{
    private function enquiry(array $overrides = []): Enquiry
    {
        /* created_at is not fillable, so it is set after the insert — which is also why the list
           falls back to id when two share a second. */
        $at = $overrides['created_at'] ?? null;
        unset($overrides['created_at']);

        $enquiry = Enquiry::create(array_merge([
            'name' => 'Janet Reid',
            'email' => 'janet@example.com',
            'phone' => '0400 111 222',
            'suburb' => 'Glen Iris',
            'message' => 'Helping my mother think about selling.',
            'consented' => true,
            'page_slug' => '/contact',
        ], $overrides));

        if ($at !== null) {
            $enquiry->forceFill(['created_at' => $at])->save();
        }

        return $enquiry;
    }

    public function test_the_newest_is_first(): void
    {
        $this->enquiry(['name' => 'Oldest', 'created_at' => now()->subDays(2)]);
        $this->enquiry(['name' => 'Newest', 'created_at' => now()]);
        $this->enquiry(['name' => 'Middle', 'created_at' => now()->subDay()]);

        $this->get('/cms/enquiries')->assertOk()->assertInertia(function ($page) {
            $this->assertSame(
                ['Newest', 'Middle', 'Oldest'],
                array_column($page->toArray()['props']['enquiries'], 'name'),
            );
        });
    }

    /** The question the screen answers is "what still needs a reply", so that is what it opens on. */
    public function test_it_shows_what_nobody_has_dealt_with_by_default(): void
    {
        $this->enquiry(['name' => 'Waiting']);
        $this->enquiry(['name' => 'Picked up', 'status' => Enquiry::IN_PROGRESS]);
        $this->enquiry(['name' => 'Done', 'status' => Enquiry::DEALT_WITH]);

        $names = fn (string $url) => array_column(
            $this->get($url)->assertOk()->toArray()['props']['enquiries'] ?? [],
            'name',
        );

        /* "Waiting for a reply" has to include something picked up but not finished, or work in
           hand vanishes from the one view anybody keeps open. */
        $this->get('/cms/enquiries')->assertInertia(function ($page) {
            $this->assertSame(['Picked up', 'Waiting'], array_column($page->toArray()['props']['enquiries'], 'name'));
            $this->assertSame(2, $page->toArray()['props']['counts']['new']);
            $this->assertSame(3, $page->toArray()['props']['counts']['all']);
        });

        $this->get('/cms/enquiries?show=handled')->assertInertia(fn ($page) => $this->assertSame(
            ['Done'], array_column($page->toArray()['props']['enquiries'], 'name'),
        ));

        $this->get('/cms/enquiries?show=all')->assertInertia(fn ($page) => $this->assertCount(
            3, $page->toArray()['props']['enquiries'],
        ));
    }

    public function test_every_status_records_when_it_changed_and_can_be_walked_back(): void
    {
        $enquiry = $this->enquiry();

        $this->assertSame(Enquiry::NEW, $enquiry->status);
        $this->assertNull($enquiry->status_changed_at);

        foreach ([Enquiry::IN_PROGRESS, Enquiry::DEALT_WITH, Enquiry::NEW] as $status) {
            $this->patch("/cms/enquiries/{$enquiry->id}/status", ['status' => $status])->assertRedirect();

            $enquiry->refresh();

            $this->assertSame($status, $enquiry->status);
            $this->assertNotNull($enquiry->status_changed_at);
        }
    }

    public function test_an_invented_status_is_refused(): void
    {
        $enquiry = $this->enquiry();

        $this->patch("/cms/enquiries/{$enquiry->id}/status", ['status' => 'archived'])
            ->assertSessionHasErrors('status');
        $this->patch("/cms/enquiries/{$enquiry->id}/status", [])->assertSessionHasErrors('status');

        $this->assertSame(Enquiry::NEW, $enquiry->refresh()->status);
    }

    /**
     * Opening one is what marks it read, and the header's counter has to fall in that same
     * response — a badge that needs a reload to catch up is a badge nobody trusts.
     */
    public function test_opening_an_enquiry_marks_it_read_in_the_same_response(): void
    {
        $enquiry = $this->enquiry();

        $this->get('/cms/enquiries')->assertInertia(fn ($page) => $this->assertSame(
            1, $page->toArray()['props']['notifications']['unread'],
        ));
        $this->assertNull($enquiry->refresh()->read_at);

        $this->get("/cms/enquiries?show=all&open={$enquiry->id}")->assertOk()->assertInertia(function ($page) {
            $this->assertSame(0, $page->toArray()['props']['notifications']['unread']);
            $this->assertNotNull($page->toArray()['props']['enquiries'][0]['readAt']);
        });

        $this->assertNotNull($enquiry->refresh()->read_at);
    }

    public function test_reading_one_twice_does_not_move_when_it_was_read(): void
    {
        $enquiry = $this->enquiry();

        $this->get("/cms/enquiries?open={$enquiry->id}");
        $first = $enquiry->refresh()->read_at;

        $this->travel(5)->minutes();
        $this->get("/cms/enquiries?open={$enquiry->id}");

        $this->assertEquals($first, $enquiry->refresh()->read_at);
    }

    public function test_listing_enquiries_reads_none_of_them(): void
    {
        $this->enquiry();
        $this->enquiry(['name' => 'Someone else']);

        $this->get('/cms/enquiries')->assertOk();

        $this->assertSame(2, Enquiry::unread()->count());
    }

    public function test_a_link_to_an_enquiry_that_is_gone_still_opens_the_screen(): void
    {
        $this->get('/cms/enquiries?open=98765')->assertOk()
            ->assertInertia(fn ($page) => $this->assertNull($page->toArray()['props']['opened']));
    }

    /**
     * The bell links to one enquiry and cannot know what filter or page it lands on, so the modal
     * is fed by id rather than by searching the rows on screen — which used to mean a link to a
     * dealt-with enquiry, or to anything past page one, opened nothing at all.
     */
    public function test_an_enquiry_outside_the_current_filter_still_opens(): void
    {
        $enquiry = $this->enquiry(['name' => 'Long since answered', 'status' => Enquiry::DEALT_WITH]);

        $this->get("/cms/enquiries?open={$enquiry->id}")->assertOk()->assertInertia(function ($page) {
            $props = $page->toArray()['props'];

            $this->assertSame([], $props['enquiries'], 'it is not in the default filter');
            $this->assertSame('Long since answered', $props['opened']['name']);
            $this->assertSame('Helping my mother think about selling.', $props['opened']['message']);
        });
    }

    private function many(int $n): void
    {
        foreach (range(1, $n) as $i) {
            $this->enquiry([
                'name' => sprintf('Sender %03d', $i),
                'email' => "s{$i}@example.com",
                'created_at' => now()->subMinutes($i),
            ]);
        }
    }

    public function test_it_pages_rather_than_capping(): void
    {
        $this->many(60);

        $this->get('/cms/enquiries')->assertOk()->assertInertia(function ($page) {
            $props = $page->toArray()['props'];

            $this->assertCount(25, $props['enquiries']);
            $this->assertSame('Sender 001', $props['enquiries'][0]['name']);
            $this->assertSame(60, $props['pagination']['total']);
            $this->assertSame(3, $props['pagination']['lastPage']);
        });

        $this->get('/cms/enquiries?page=2')->assertOk()->assertInertia(fn ($page) => $this->assertSame(
            'Sender 026', $page->toArray()['props']['enquiries'][0]['name'],
        ));

        $this->get('/cms/enquiries?per_page=100')->assertOk()->assertInertia(fn ($page) => $this->assertCount(
            60, $page->toArray()['props']['enquiries'],
        ));
    }

    /**
     * The box used to filter the rows already loaded, so with paging it would have searched a
     * twenty-fifth of the inbox and reported the rest as absent.
     */
    public function test_search_reaches_enquiries_that_are_not_on_the_first_page(): void
    {
        $this->many(60);

        $this->get('/cms/enquiries?q=Sender+058')->assertOk()->assertInertia(function ($page) {
            $props = $page->toArray()['props'];

            $this->assertSame(['Sender 058'], array_column($props['enquiries'], 'name'));
            $this->assertSame(1, $props['pagination']['total']);
        });
    }

    public function test_search_looks_at_the_message_as_well_as_the_sender(): void
    {
        $this->enquiry(['name' => 'Someone', 'message' => 'We are downsizing to a unit in Ballarat.']);
        $this->enquiry(['name' => 'Someone else', 'message' => 'Nothing relevant.']);

        $this->get('/cms/enquiries?q=Ballarat')->assertOk()->assertInertia(fn ($page) => $this->assertSame(
            ['Someone'], array_column($page->toArray()['props']['enquiries'], 'name'),
        ));
    }

    /* LIKE treats both as wildcards, so an unescaped term would match every row instead of none. */
    public function test_a_search_for_a_wildcard_matches_nothing_rather_than_everything(): void
    {
        $this->many(5);

        $this->get('/cms/enquiries?q=%25')->assertOk()->assertInertia(fn ($page) => $this->assertSame(
            [], $page->toArray()['props']['enquiries'],
        ));
    }

    /* Long messages were most of the payload, to be shown as one clipped line. */
    public function test_a_list_row_carries_a_snippet_and_the_modal_carries_the_whole_thing(): void
    {
        $enquiry = $this->enquiry(['message' => str_repeat('a very long sentence. ', 40)]);

        $this->get("/cms/enquiries?open={$enquiry->id}")->assertOk()->assertInertia(function ($page) {
            $props = $page->toArray()['props'];

            $this->assertLessThanOrEqual(163, strlen($props['enquiries'][0]['snippet']));
            $this->assertArrayNotHasKey('message', $props['enquiries'][0]);
            $this->assertSame(880, strlen($props['opened']['message']));
        });
    }

    /**
     * The whole reason marking one dealt with is its own route rather than a general update: the
     * details are the sender's words, and a screen that could rewrite them would eventually be
     * used to.
     */
    public function test_the_details_a_visitor_sent_cannot_be_edited_here(): void
    {
        $enquiry = $this->enquiry();

        $this->patch("/cms/enquiries/{$enquiry->id}/status", [
            'status' => Enquiry::DEALT_WITH,
            'name' => 'Someone else',
            'email' => 'rewritten@example.com',
            'message' => 'Rewritten.',
        ])->assertRedirect();

        $enquiry->refresh();

        $this->assertSame('Janet Reid', $enquiry->name);
        $this->assertSame('janet@example.com', $enquiry->email);
        $this->assertSame('Helping my mother think about selling.', $enquiry->message);
        $this->assertSame(Enquiry::DEALT_WITH, $enquiry->status);
    }

    public function test_a_client_administrator_can_read_and_mark_but_not_delete(): void
    {
        $enquiry = $this->enquiry();

        $this->actingAs($this->clientAdmin());

        $this->get('/cms/enquiries')->assertOk();
        $this->patch("/cms/enquiries/{$enquiry->id}/status", ['status' => Enquiry::DEALT_WITH])->assertRedirect();
        $this->delete("/cms/enquiries/{$enquiry->id}")->assertForbidden();

        $this->assertDatabaseHas('enquiries', ['id' => $enquiry->id]);
    }

    /**
     * Gone for good rather than into Recently deleted. The reason to delete one is somebody asking
     * to be forgotten, and a bin holding a recoverable copy of the details being erased would be
     * the opposite of honouring that.
     */
    public function test_a_super_administrator_deletes_one_for_good(): void
    {
        $enquiry = $this->enquiry();

        $this->delete("/cms/enquiries/{$enquiry->id}")->assertRedirect();

        $this->assertDatabaseMissing('enquiries', ['id' => $enquiry->id]);
        $this->get('/cms/deleted')->assertOk()->assertDontSee('Janet Reid');
    }

    /** Erasing somebody while minting a permanent copy of their name in the audit log is not erasing them. */
    public function test_deleting_one_does_not_copy_the_name_into_the_audit_log(): void
    {
        $enquiry = $this->enquiry();

        $this->delete("/cms/enquiries/{$enquiry->id}")->assertRedirect();

        $this->assertDatabaseHas('activity_log', ['subject_type' => 'Enquiry', 'action' => 'deleted']);
        $this->assertDatabaseMissing('activity_log', ['subject_label' => 'Janet Reid']);
    }

    public function test_a_visitor_cannot_read_other_peoples_enquiries(): void
    {
        $enquiry = $this->enquiry();

        auth()->logout();

        $this->get('/cms/enquiries')->assertRedirect('/login');
        $this->patch("/cms/enquiries/{$enquiry->id}/status", ['status' => Enquiry::DEALT_WITH])->assertRedirect('/login');
    }

    /** The public form and this screen are two halves of one thing, so they are checked together. */
    public function test_what_the_contact_page_collects_arrives_here(): void
    {
        auth()->logout();

        $this->post('/enquiries', [
            'name' => 'Brian Todd',
            'email' => 'brian@example.com',
            'phone' => '0400 999 888',
            'suburb' => 'Geelong',
            'message' => 'Thinking about downsizing next spring.',
            'consent' => true,
            'page' => '/contact',
        ])->assertRedirect();

        $this->actingAs($this->superAdmin());

        $this->get('/cms/enquiries')->assertOk()->assertInertia(function ($page) {
            $enquiry = $page->toArray()['props']['enquiries'][0];

            $this->assertSame('Brian Todd', $enquiry['name']);
            $this->assertSame(Enquiry::NEW, $enquiry['status']);
            $this->assertNull($enquiry['readAt']);
        });

        /* Everything the form collected, which the list row deliberately no longer carries. */
        $id = Enquiry::where('email', 'brian@example.com')->sole()->id;

        $this->get("/cms/enquiries?open={$id}")->assertOk()->assertInertia(function ($page) {
            $opened = $page->toArray()['props']['opened'];

            $this->assertSame('Brian Todd', $opened['name']);
            $this->assertSame('brian@example.com', $opened['email']);
            $this->assertSame('0400 999 888', $opened['phone']);
            $this->assertSame('Geelong', $opened['suburb']);
            $this->assertSame('Thinking about downsizing next spring.', $opened['message']);
            $this->assertSame('/contact', $opened['page']);
        });
    }
}
