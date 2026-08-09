<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\Enquiry;
use App\Models\Faq;
use App\Models\Media;
use App\Models\Testimonial;
use App\Models\User;
use Tests\TestCase;

class SearchTest extends TestCase
{
    private function search(string $term): array
    {
        return $this->getJson('/cms/search?q='.urlencode($term))->assertOk()->json('groups');
    }

    private function titlesIn(array $groups, string $label): array
    {
        foreach ($groups as $group) {
            if ($group['label'] === $label) {
                return array_column($group['results'], 'title');
            }
        }

        return [];
    }

    /**
     * The link is followed rather than pattern-matched. Pages are keyed on `cms_id` in the
     * builder's route and on `id` everywhere else, so a link of the right shape built from the
     * wrong number still 404s — which is what a regex on the href happily let through.
     */
    public function test_it_finds_a_page_and_the_link_reaches_its_builder(): void
    {
        $pages = collect($this->search('How it works'))->firstWhere('label', 'Pages');

        $this->assertNotNull($pages);
        $this->assertSame('How it works', $pages['results'][0]['title']);
        $this->get($pages['results'][0]['href'])->assertOk();
    }

    public function test_an_article_link_reaches_its_editor(): void
    {
        BlogPost::create([
            'slug' => 'zebra-article', 'title' => 'Zebra article', 'body' => '<p>Body.</p>', 'status' => 'published',
        ]);

        $articles = collect($this->search('Zebra'))->firstWhere('label', 'Articles');

        $this->get($articles['results'][0]['href'])->assertOk();
    }

    public function test_it_reaches_every_content_kind(): void
    {
        $post = BlogPost::create([
            'slug' => 'zebra-article', 'title' => 'Zebra article', 'body' => '<p>Body.</p>', 'status' => 'published',
        ]);
        Faq::create(['question' => 'Zebra question?', 'answer' => 'Yes.', 'active' => true]);
        Testimonial::create(['name' => 'Zebra Client', 'quote' => 'Lovely.', 'active' => true]);
        Media::create([
            'key' => '2026/08/zebra.jpg', 'name' => 'Zebra photo', 'mime' => 'image/jpeg',
            'size' => 1024, 'disk' => 's3',
        ]);
        Enquiry::create(['name' => 'Zebra Enquirer', 'email' => 'zebra@example.com', 'consented' => true]);

        $groups = $this->search('Zebra');

        $this->assertSame(['Zebra article'], $this->titlesIn($groups, 'Articles'));
        $this->assertSame(['Zebra question?'], $this->titlesIn($groups, 'FAQs'));
        $this->assertSame(['Zebra Client'], $this->titlesIn($groups, 'Testimonials'));
        $this->assertSame(['Zebra photo'], $this->titlesIn($groups, 'Media'));
        $this->assertSame(['Zebra Enquirer'], $this->titlesIn($groups, 'Enquiries'));

        $this->assertSame("/cms/blog/{$post->id}/edit", collect($groups)->firstWhere('label', 'Articles')['results'][0]['href']);
    }

    public function test_an_article_body_is_searched_but_never_returned(): void
    {
        BlogPost::create([
            'slug' => 'body-only', 'title' => 'Nothing in the title', 'status' => 'published',
            'body' => '<p>The word aardvark appears only in here.</p>',
        ]);

        $groups = $this->search('aardvark');

        $this->assertSame(['Nothing in the title'], $this->titlesIn($groups, 'Articles'));
        $this->assertArrayNotHasKey('body', collect($groups)->firstWhere('label', 'Articles')['results'][0]);
    }

    /* Somebody's account of their own circumstances is not an index for a colleague to browse. */
    public function test_an_enquiry_message_is_not_searchable(): void
    {
        Enquiry::create([
            'name' => 'Janet Reid', 'email' => 'janet@example.com', 'consented' => true,
            'message' => 'My husband has dementia and we must sell.',
        ]);

        $this->assertSame([], $this->titlesIn($this->search('dementia'), 'Enquiries'));
        $this->assertSame(['Janet Reid'], $this->titlesIn($this->search('Janet'), 'Enquiries'));
    }

    public function test_a_deleted_article_is_not_found(): void
    {
        $post = BlogPost::create([
            'slug' => 'gone', 'title' => 'Zebra article', 'body' => '<p>Body.</p>', 'status' => 'published',
        ]);
        $post->delete();

        $this->assertSame([], $this->titlesIn($this->search('Zebra'), 'Articles'));
    }

    /* LIKE treats both as wildcards, so an unescaped term would match every row instead of none. */
    public function test_wildcard_characters_are_taken_literally(): void
    {
        BlogPost::create([
            'slug' => 'percent', 'title' => 'Save 50% on fees', 'body' => '<p>Body.</p>', 'status' => 'published',
        ]);
        BlogPost::create([
            'slug' => 'other', 'title' => 'Something else entirely', 'body' => '<p>Body.</p>', 'status' => 'published',
        ]);

        $this->assertSame(['Save 50% on fees'], $this->titlesIn($this->search('50%'), 'Articles'));
        $this->assertSame([], $this->titlesIn($this->search('%_%'), 'Articles'));
    }

    public function test_a_term_under_two_letters_searches_nothing(): void
    {
        $this->assertSame([], $this->search('a'));
        $this->assertSame([], $this->search(' '));
    }

    public function test_each_group_is_capped(): void
    {
        foreach (range(1, 8) as $n) {
            Faq::create(['question' => "Wombat question {$n}?", 'answer' => 'Yes.', 'active' => true]);
        }

        $this->assertCount(5, $this->titlesIn($this->search('Wombat'), 'FAQs'));
    }

    public function test_a_client_administrator_may_search_and_a_visitor_may_not(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::CLIENT_ADMIN, 'is_active' => true]));
        $this->getJson('/cms/search?q=How')->assertOk();

        auth()->logout();
        $this->get('/cms/search?q=How')->assertRedirect('/login');
    }
}
