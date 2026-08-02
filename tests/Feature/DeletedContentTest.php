<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\BlogPost;
use App\Models\Faq;
use App\Models\Media;
use App\Models\Testimonial;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DeletedContentTest extends TestCase
{
    private function question(string $text = 'What does it cost?'): Faq
    {
        return Faq::create(['question' => $text, 'answer' => 'Nothing.', 'sort_order' => 1, 'active' => true]);
    }

    public function test_a_deleted_question_leaves_the_website_but_can_be_brought_back(): void
    {
        $faq = $this->question();

        $this->delete("/cms/faqs/{$faq->id}")->assertRedirect();

        /* Gone everywhere a reader or an editor looks. */
        $this->assertSame(0, Faq::count());
        $this->get('/cms/faqs')->assertOk()->assertInertia(
            fn ($p) => $p->where('faqs', []),
        );

        $this->post("/cms/deleted/question/{$faq->id}/restore")->assertRedirect();

        $this->assertSame(1, Faq::count());
        $this->assertSame('What does it cost?', Faq::sole()->question);
    }

    public function test_the_screen_lists_everything_deleted_newest_first(): void
    {
        $this->question('Older')->delete();
        $this->travel(1)->minutes();
        Testimonial::create(['name' => 'Janet R.', 'quote' => 'Kind.', 'sort_order' => 1])->delete();

        $this->get('/cms/deleted')->assertOk()->assertInertia(function ($page) {
            $items = $page->toArray()['props']['items'];

            $this->assertSame(['testimonial', 'question'], array_column($items, 'kind'));
            $this->assertSame('Janet R.', $items[0]['label']);
        });
    }

    public function test_deleting_for_good_is_the_one_thing_that_cannot_be_undone(): void
    {
        $faq = $this->question();
        $faq->delete();

        $this->delete("/cms/deleted/question/{$faq->id}")->assertRedirect();

        $this->assertDatabaseMissing('faqs', ['id' => $faq->id]);
        $this->assertSame('destroyed', Activity::latest('id')->first()->action);
    }

    public function test_restoring_and_destroying_are_told_apart_in_the_log(): void
    {
        $faq = $this->question();

        $faq->delete();
        $faq->restore();

        $this->assertSame(
            ['created', 'deleted', 'restored'],
            Activity::where('subject_type', 'Faq')->orderBy('id')->pluck('action')->all(),
        );
    }

    public function test_a_client_administrator_cannot_reach_the_screen_or_restore(): void
    {
        $faq = $this->question();
        $faq->delete();

        $this->actingAs($this->clientAdmin());

        $this->get('/cms/deleted')->assertForbidden();
        $this->post("/cms/deleted/question/{$faq->id}/restore")->assertForbidden();
        $this->delete("/cms/deleted/question/{$faq->id}")->assertForbidden();
    }

    public function test_a_deleted_article_keeps_hold_of_its_web_address(): void
    {
        $this->post('/cms/blog', ['title' => 'Planning a downsize', 'body' => '<p>Words.</p>'])->assertRedirect();

        $first = BlogPost::sole();
        $first->delete();

        /* The slug column is unique and the row is still there, so a second article of the same
           name has to be given a different address rather than failing to save. */
        $this->post('/cms/blog', ['title' => 'Planning a downsize', 'body' => '<p>Words.</p>'])->assertRedirect();

        $this->assertSame('planning-a-downsize-2', BlogPost::sole()->slug);
    }

    public function test_an_image_a_deleted_article_still_needs_cannot_be_deleted(): void
    {
        Storage::fake('s3');

        $medium = Media::create([
            'key' => '2026/08/hero.jpg', 'name' => 'hero.jpg', 'mime' => 'image/jpeg',
            'size' => 1024, 'disk' => 's3',
        ]);

        $this->post('/cms/blog', [
            'title' => 'With a picture',
            'body' => '<p>Words.</p>',
            'featured_image' => $medium->url(),
        ])->assertRedirect();

        BlogPost::sole()->delete();

        /* Restoring the article later would otherwise bring back a broken image. */
        $usage = $this->postJson('/cms/media/usage', ['ids' => [$medium->id]])->json('items.0.usedBy');

        $this->assertNotEmpty($usage);
    }
}
