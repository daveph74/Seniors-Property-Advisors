<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\Enquiry;
use App\Models\Faq;
use App\Models\Page;
use App\Models\Testimonial;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    private function counts(): array
    {
        $props = $this->get('/cms')->assertOk()->viewData('page')['props'];

        return collect($props['counts'])->mapWithKeys(fn ($c) => [$c['label'] => $c['n']])->all();
    }

    public function test_every_count_is_the_real_one(): void
    {
        Faq::create(['question' => 'Shown', 'answer' => 'a', 'sort_order' => 1, 'active' => true]);
        Faq::create(['question' => 'Hidden', 'answer' => 'b', 'sort_order' => 2, 'active' => false]);

        Testimonial::create([
            'name' => 'Janet R.', 'quote' => 'Kind.', 'sort_order' => 1, 'active' => true,
            'consent_confirmed_at' => now(), 'consent_confirmed_by' => 'Helen',
        ]);
        /* Active but with no recorded permission: it cannot reach a reader, so it is not "active". */
        Testimonial::create(['name' => 'No permission', 'quote' => 'x', 'sort_order' => 2, 'active' => true]);

        $counts = $this->counts();

        $this->assertSame(1, $counts['Active FAQs']);
        $this->assertSame(1, $counts['Active testimonials']);
        $this->assertSame(Page::where('status', 'published')->count(), $counts['Published pages']);
    }

    public function test_it_counts_drafts_and_published_separately(): void
    {
        $this->post('/cms/blog', ['title' => 'A draft', 'body' => '<p>x</p>'])->assertRedirect();

        $counts = $this->counts();

        $this->assertSame(1, $counts['Draft articles']);
        $this->assertSame(0, $counts['Published articles']);

        BlogPost::sole()->update(['status' => 'published', 'published_at' => now()]);

        $counts = $this->counts();

        $this->assertSame(0, $counts['Draft articles']);
        $this->assertSame(1, $counts['Published articles']);
    }

    public function test_a_published_page_holding_unsaved_changes_is_waiting_to_be_published(): void
    {
        $page = Page::create([
            'cms_id' => Page::max('cms_id') + 1,
            'slug' => 'about', 'url' => '/about', 'title' => 'About us', 'status' => 'published',
            'published' => [['id' => 'a', 'type' => 'hero', 'active' => true, 'data' => []]],
            'draft' => [['id' => 'a', 'type' => 'hero', 'active' => true, 'data' => ['heading' => 'New']]],
        ]);

        $waiting = $this->get('/cms')->viewData('page')['props']['awaitingPublication'];

        /* The case that gets forgotten: the work is finished and nobody pressed publish. */
        $this->assertContains('About us', array_column($waiting, 'title'));
        $this->assertSame(
            'Live, with unpublished changes',
            collect($waiting)->firstWhere('title', 'About us')['sub'],
        );
        $this->assertSame('/cms/pages/'.$page->cms_id.'/edit', collect($waiting)->firstWhere('title', 'About us')['href']);
    }

    public function test_recently_edited_mixes_pages_and_articles_newest_first(): void
    {
        /* Seeded content shares this second, so the article is nudged forward rather than relying
           on a tie between identical timestamps. */
        $this->travel(1)->minutes();
        $this->post('/cms/blog', ['title' => 'Written last', 'body' => '<p>x</p>'])->assertRedirect();

        $recent = $this->get('/cms')->viewData('page')['props']['recentlyEdited'];

        $this->assertSame('Written last', $recent[0]['title']);
        $this->assertSame('article', $recent[0]['kind']);
        $this->assertContains('page', array_column($recent, 'kind'));
    }

    public function test_the_summary_line_says_what_is_true(): void
    {
        $this->post('/cms/blog', ['title' => 'A draft', 'body' => '<p>x</p>'])->assertRedirect();

        $props = $this->get('/cms')->viewData('page')['props'];

        /* It used to read "three pages have unpublished changes and two articles are waiting for
           review" whatever the state of the site, including an empty one. */
        $this->assertStringContainsString('not yet live', $props['greeting']['summary']);

        BlogPost::query()->forceDelete();
        Page::where('status', 'draft')->delete();
        Enquiry::query()->delete();

        $props = $this->get('/cms')->viewData('page')['props'];

        $this->assertSame(
            'Everything written is published, and there is nothing waiting.',
            $props['greeting']['summary'],
        );
    }

    public function test_it_counts_only_enquiries_nobody_has_dealt_with(): void
    {
        Enquiry::create(['name' => 'Waiting', 'email' => 'a@example.com', 'consented' => true]);
        Enquiry::create([
            'name' => 'Dealt with', 'email' => 'b@example.com', 'consented' => true,
        ])->update(['handled_at' => now()]);

        $this->assertSame(1, $this->counts()['New enquiries']);
    }

    public function test_the_activity_feed_reads_the_log_rather_than_inventing_one(): void
    {
        Faq::create(['question' => 'Just added', 'answer' => 'a', 'sort_order' => 1]);

        $activity = $this->get('/cms')->viewData('page')['props']['activity'];

        $this->assertSame('created', $activity[0]['action']);
        $this->assertSame('Just added', $activity[0]['subject']);
        $this->assertSame('Faq', $activity[0]['type']);
    }

    public function test_a_guest_cannot_see_the_dashboard(): void
    {
        auth()->logout();

        $this->get('/cms')->assertRedirect('/login');
    }
}
