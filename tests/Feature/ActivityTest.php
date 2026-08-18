<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\BlogPost;
use App\Models\Faq;
use App\Models\Page;
use App\Models\Testimonial;
use Tests\TestCase;

class ActivityTest extends TestCase
{
    private function actions(?string $type = null): array
    {
        return Activity::query()
            ->when($type, fn ($q) => $q->where('subject_type', $type))
            ->orderBy('id')
            ->pluck('action')
            ->all();
    }

    public function test_it_records_every_step_of_an_article_from_writing_to_deleting(): void
    {
        $this->post('/cms/blog', ['title' => 'Planning a downsize', 'body' => '<p>Words.</p>'])
            ->assertRedirect();

        $post = BlogPost::sole();

        $this->post("/cms/blog/{$post->id}/publish")->assertRedirect();
        $this->post("/cms/blog/{$post->id}/unpublish")->assertRedirect();
        $this->post("/cms/blog/{$post->id}/archive")->assertRedirect();
        $this->post("/cms/blog/{$post->id}/unarchive")->assertRedirect();
        $this->delete("/cms/blog/{$post->id}")->assertRedirect();

        /* Scope §13's list, in the order it happened. */
        $this->assertSame(
            ['created', 'published', 'unpublished', 'archived', 'restored', 'deleted'],
            $this->actions('BlogPost'),
        );
    }

    public function test_it_records_who_did_it_and_when(): void
    {
        $this->actingAs($this->superAdmin(['name' => 'Helen Marsh']));

        Faq::create(['question' => 'What does it cost?', 'answer' => 'Nothing.', 'sort_order' => 1]);

        /* Scoped to the FAQ rather than sole(): seeding the default pages writes its own
           entries, attributed to System, and those are not what this asserts. */
        $entry = Activity::where('subject_type', 'Faq')->sole();

        $this->assertSame('Helen Marsh', $entry->by_name);
        $this->assertNotNull($entry->by_id);
        $this->assertNotNull($entry->created_at);
    }

    public function test_an_entry_still_reads_after_its_subject_is_gone(): void
    {
        $faq = Faq::create(['question' => 'How long does it take?', 'answer' => 'Weeks.', 'sort_order' => 1]);

        $faq->delete();

        $deleted = Activity::where('action', 'deleted')->sole();

        /* The label is a copy for exactly this: a join would leave the interesting entries blank. */
        $this->assertSame('How long does it take?', $deleted->subject_label);
        $this->assertSame(0, Faq::count());
    }

    public function test_editing_is_told_apart_from_publishing(): void
    {
        $t = Testimonial::create(['name' => 'Janet R.', 'quote' => 'Kind people.', 'sort_order' => 1]);

        $t->update(['location' => 'Glen Iris']);

        $this->assertSame(['created', 'edited'], $this->actions('Testimonial'));
    }

    public function test_showing_and_hiding_reads_as_publishing_not_editing(): void
    {
        /* A question has no status column — showing it is the `active` flag, and §13 asks for
           published and unpublished rather than "edited" over and over. */
        $faq = Faq::create(['question' => 'Shown then hidden', 'answer' => 'a', 'sort_order' => 1, 'active' => false]);

        $faq->update(['active' => true]);
        $faq->update(['active' => false]);

        $this->assertSame(['created', 'published', 'unpublished'], $this->actions('Faq'));
    }

    public function test_a_page_records_its_publishing(): void
    {
        $this->post('/cms/pages', ['title' => 'Our services', 'layout' => 'blank'])->assertRedirect();

        $page = Page::where('slug', 'our-services')->sole();

        $this->post("/cms/pages/{$page->cms_id}/publish-now")->assertRedirect();
        $this->post("/cms/pages/{$page->cms_id}/unpublish")->assertRedirect();

        $this->assertContains('published', $this->actions('Page'));
        $this->assertContains('unpublished', $this->actions('Page'));
    }

    public function test_the_screen_lists_what_happened_newest_first(): void
    {
        Faq::create(['question' => 'First asked', 'answer' => 'a', 'sort_order' => 1]);
        Faq::create(['question' => 'Asked later', 'answer' => 'b', 'sort_order' => 2]);

        $this->get('/cms/activity')->assertOk()->assertInertia(function ($page) {
            $entries = $page->toArray()['props']['entries'];

            $this->assertSame('Asked later', $entries[0]['label']);
            $this->assertSame('created', $entries[0]['action']);
            $this->assertSame('Faq', $entries[0]['type']);
        });
    }

    public function test_the_screen_can_be_narrowed_to_one_kind_of_action(): void
    {
        $faq = Faq::create(['question' => 'Kept', 'answer' => 'a', 'sort_order' => 1]);
        Faq::create(['question' => 'Removed', 'answer' => 'b', 'sort_order' => 2])->delete();

        $this->get('/cms/activity?action=deleted')->assertOk()->assertInertia(function ($page) {
            $entries = $page->toArray()['props']['entries'];

            $this->assertCount(1, $entries);
            $this->assertSame('Removed', $entries[0]['label']);
        });

        $this->assertSame('Kept', $faq->refresh()->question);
    }

    public function test_a_guest_cannot_read_the_log(): void
    {
        auth()->logout();

        $this->get('/cms/activity')->assertRedirect('/login');
    }
}
