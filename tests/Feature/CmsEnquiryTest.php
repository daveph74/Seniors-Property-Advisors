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
        $this->enquiry(['name' => 'Done', 'handled_at' => now()]);

        $names = fn (string $url) => array_column(
            $this->get($url)->assertOk()->toArray()['props']['enquiries'] ?? [],
            'name',
        );

        $this->get('/cms/enquiries')->assertInertia(function ($page) {
            $this->assertSame(['Waiting'], array_column($page->toArray()['props']['enquiries'], 'name'));
            $this->assertSame(1, $page->toArray()['props']['counts']['new']);
            $this->assertSame(2, $page->toArray()['props']['counts']['all']);
        });

        $this->get('/cms/enquiries?show=handled')->assertInertia(fn ($page) => $this->assertSame(
            ['Done'], array_column($page->toArray()['props']['enquiries'], 'name'),
        ));

        $this->get('/cms/enquiries?show=all')->assertInertia(fn ($page) => $this->assertCount(
            2, $page->toArray()['props']['enquiries'],
        ));
    }

    public function test_marking_one_dealt_with_records_when_and_can_be_undone(): void
    {
        $enquiry = $this->enquiry();

        $this->patch("/cms/enquiries/{$enquiry->id}/handled", ['handled' => true])->assertRedirect();
        $this->assertNotNull($enquiry->refresh()->handled_at);

        $this->patch("/cms/enquiries/{$enquiry->id}/handled", ['handled' => false])->assertRedirect();
        $this->assertNull($enquiry->refresh()->handled_at);
    }

    /**
     * The whole reason marking one dealt with is its own route rather than a general update: the
     * details are the sender's words, and a screen that could rewrite them would eventually be
     * used to.
     */
    public function test_the_details_a_visitor_sent_cannot_be_edited_here(): void
    {
        $enquiry = $this->enquiry();

        $this->patch("/cms/enquiries/{$enquiry->id}/handled", [
            'handled' => true,
            'name' => 'Someone else',
            'email' => 'rewritten@example.com',
            'message' => 'Rewritten.',
        ])->assertRedirect();

        $enquiry->refresh();

        $this->assertSame('Janet Reid', $enquiry->name);
        $this->assertSame('janet@example.com', $enquiry->email);
        $this->assertSame('Helping my mother think about selling.', $enquiry->message);
        $this->assertNotNull($enquiry->handled_at);
    }

    public function test_a_client_administrator_can_read_and_mark_but_not_delete(): void
    {
        $enquiry = $this->enquiry();

        $this->actingAs($this->clientAdmin());

        $this->get('/cms/enquiries')->assertOk();
        $this->patch("/cms/enquiries/{$enquiry->id}/handled", ['handled' => true])->assertRedirect();
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
        $this->patch("/cms/enquiries/{$enquiry->id}/handled", ['handled' => true])->assertRedirect('/login');
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
            $this->assertSame('/contact', $enquiry['page']);
            $this->assertNull($enquiry['handledAt']);
        });
    }
}
