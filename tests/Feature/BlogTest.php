<?php

namespace Tests\Feature;

use App\Content\ContentLibrary;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Page;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class BlogTest extends TestCase
{
    private function category(string $name = 'Downsizing', bool $active = true): BlogCategory
    {
        return BlogCategory::create([
            'name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)),
            'sort_order' => BlogCategory::max('sort_order') + 1,
            'active' => $active,
        ]);
    }

    private function article(array $overrides = []): BlogPost
    {
        return BlogPost::create(array_merge([
            'slug' => 'an-article',
            'title' => 'An article',
            'summary' => 'A short summary.',
            'body' => 'The body.',
            'status' => 'published',
            'published_at' => now()->subDay(),
            'author_name' => 'Helen Marsh',
        ], $overrides));
    }

    private function write(array $body = []): TestResponse
    {
        return $this->post('/cms/blog', array_merge([
            'title' => 'When to start planning',
            'summary' => 'A guide for families.',
            'body' => "## A heading\n\nSome **bold** text.",
        ], $body));
    }

    public function test_an_article_can_be_written_edited_and_published(): void
    {
        $this->write()->assertRedirect();

        $post = BlogPost::sole();

        $this->assertSame('when-to-start-planning', $post->slug);
        $this->assertSame('draft', $post->status);

        $this->get("/blog/{$post->slug}")->assertNotFound();

        $this->post("/cms/blog/{$post->id}/publish")->assertRedirect();

        $post->refresh();

        $this->assertSame('published', $post->status);
        $this->assertNotNull($post->published_at);
        $this->get('/blog/when-to-start-planning')->assertOk();
    }

    public function test_only_published_articles_are_public(): void
    {
        $draft = $this->article(['slug' => 'a-draft', 'status' => 'draft']);
        $archived = $this->article(['slug' => 'an-archive', 'status' => 'archived']);
        $live = $this->article(['slug' => 'a-live-one']);

        $this->get("/blog/{$draft->slug}")->assertNotFound();
        $this->get("/blog/{$archived->slug}")->assertNotFound();
        $this->get("/blog/{$live->slug}")->assertOk();

        $slugs = array_column((new ContentLibrary)->posts()['posts'], 'slug');

        $this->assertSame(['a-live-one'], $slugs);
    }

    public function test_unpublishing_and_archiving_take_an_article_off_the_website(): void
    {
        $post = $this->article();

        $this->post("/cms/blog/{$post->id}/unpublish")->assertRedirect();
        $this->get("/blog/{$post->slug}")->assertNotFound();

        $this->post("/cms/blog/{$post->id}/archive")->assertRedirect();
        $this->assertSame('archived', $post->refresh()->status);

        $this->post("/cms/blog/{$post->id}/unarchive")->assertRedirect();
        $this->assertSame('published', $post->refresh()->status);
        $this->get("/blog/{$post->slug}")->assertOk();
    }

    public function test_the_body_renders_every_format_the_scope_asks_for(): void
    {
        $post = $this->article(['body' => <<<'MD'
            ## A heading

            Some **bold** and *italic* text with a [link](https://example.com).

            - first
            - second

            1. one
            2. two

            > A quotation.

            | Column | Other |
            | --- | --- |
            | a | b |

            ![A photo](/media/2026/07/photo.png)
            MD]);

        $html = $post->renderedBody();

        foreach (['<h2>', '<strong>', '<em>', '<a href="https://example.com">', '<ul>', '<ol>', '<blockquote>', '<table>', '<td>', '<img src="/media/2026/07/photo.png"'] as $needle) {
            $this->assertStringContainsString($needle, $html, "missing {$needle}");
        }
    }

    public function test_html_in_a_stored_body_can_never_reach_a_reader(): void
    {
        $post = $this->article(['body' => 'Before <script>alert(1)</script> after <iframe src="x"></iframe>']);

        $html = $post->renderedBody();

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringContainsString('Before', $html);

        $this->get("/blog/{$post->slug}")->assertOk()->assertInertia(function ($props) {
            $this->assertStringNotContainsString('<script', $props->toArray()['props']['article']['body']);
        });
    }

    public function test_pasted_html_is_refused_with_a_clear_message(): void
    {
        $this->write(['body' => 'Hello <script>alert(1)</script>'])->assertSessionHasErrors('body');
        $this->write(['body' => '<div onclick="steal()">Tap</div>'])->assertSessionHasErrors('body');

        $this->assertSame(0, BlogPost::count());
    }

    public function test_a_future_date_does_not_hide_a_published_article(): void
    {
        $post = $this->article(['published_at' => now()->addMonth()]);

        $this->get("/blog/{$post->slug}")->assertOk();
        $this->assertCount(1, (new ContentLibrary)->posts()['posts']);
    }

    public function test_slugs_are_unique_and_validated(): void
    {
        $this->write(['title' => 'Same title'])->assertRedirect();
        $this->write(['title' => 'Same title'])->assertRedirect();

        $this->assertSame(['same-title', 'same-title-2'], BlogPost::orderBy('id')->pluck('slug')->all());

        $this->write(['title' => 'Bad', 'slug' => 'Not Valid!'])->assertSessionHasErrors('slug');
    }

    public function test_the_load_more_route_is_never_taken_as_an_article_slug(): void
    {
        $this->write(['title' => 'Articles'])->assertRedirect();

        $this->assertSame('articles-2', BlogPost::sole()->slug);
    }

    public function test_an_article_can_hold_more_than_one_category(): void
    {
        $downsizing = $this->category('Downsizing');
        $finance = $this->category('Finance');

        $this->write(['categories' => [$downsizing->id, $finance->id]])->assertRedirect();

        $this->assertSame(['Downsizing', 'Finance'], BlogPost::sole()->categories->pluck('name')->sort()->values()->all());
    }

    public function test_categories_can_be_created_reordered_and_disabled(): void
    {
        $this->post('/cms/blog-categories', ['name' => 'Downsizing'])->assertRedirect();
        $this->post('/cms/blog-categories', ['name' => 'Finance'])->assertRedirect();

        $first = BlogCategory::where('name', 'Downsizing')->sole();
        $second = BlogCategory::where('name', 'Finance')->sole();

        $this->post('/cms/blog-categories/reorder', ['ids' => [$second->id, $first->id]])->assertRedirect();

        $this->assertSame(['Finance', 'Downsizing'], BlogCategory::ordered()->pluck('name')->all());

        $this->patch("/cms/blog-categories/{$first->id}", ['name' => 'Downsizing', 'active' => false])
            ->assertRedirect();

        $this->assertFalse($first->refresh()->active);
    }

    public function test_a_disabled_category_disappears_from_filters_but_keeps_its_articles_live(): void
    {
        $hidden = $this->category('Retired topic', active: false);
        $shown = $this->category('Finance');

        $post = $this->article();
        $post->categories()->sync([$hidden->id, $shown->id]);

        $library = (new ContentLibrary)->posts();

        $this->assertSame(['Finance'], $library['postCategories']);
        $this->assertCount(1, $library['posts']);
        $this->get("/blog/{$post->slug}")->assertOk();
    }

    public function test_related_articles_share_a_category_and_exclude_the_article_itself(): void
    {
        $category = $this->category();

        $post = $this->article(['slug' => 'the-one']);
        $friend = $this->article(['slug' => 'a-friend']);
        $stranger = $this->article(['slug' => 'a-stranger']);

        $post->categories()->sync([$category->id]);
        $friend->categories()->sync([$category->id]);

        $related = array_column((new ContentLibrary)->related($post->load('categories')), 'slug');

        $this->assertSame(['a-friend'], $related);
        $this->assertNotContains($stranger->slug, $related);
    }

    public function test_load_more_returns_the_next_page_of_published_articles(): void
    {
        foreach (range(1, ContentLibrary::PER_PAGE + 3) as $n) {
            $this->article(['slug' => "article-{$n}", 'published_at' => now()->subDays($n)]);
        }

        $this->article(['slug' => 'a-draft', 'status' => 'draft']);

        $first = (new ContentLibrary)->posts();

        $this->assertCount(ContentLibrary::PER_PAGE, $first['posts']);
        $this->assertTrue($first['hasMorePosts']);

        $second = $this->get('/blog/articles?page=2')->assertOk()->json();

        $this->assertCount(3, $second['posts']);
        $this->assertFalse($second['hasMorePosts']);
        $this->assertNotContains('a-draft', array_column($second['posts'], 'slug'));
    }

    public function test_a_client_administrator_can_write_and_publish_but_not_delete(): void
    {
        $post = $this->article();

        $this->actingAs($this->clientAdmin());

        $this->write()->assertRedirect();
        $this->post("/cms/blog/{$post->id}/publish")->assertRedirect();
        $this->post("/cms/blog/{$post->id}/archive")->assertRedirect();

        $this->post("/cms/blog/{$post->id}/unarchive")->assertForbidden();
        $this->delete("/cms/blog/{$post->id}")->assertForbidden();

        $this->assertDatabaseHas('blog_posts', ['id' => $post->id]);
    }

    public function test_a_super_administrator_can_delete_an_article(): void
    {
        $post = $this->article();

        $this->delete("/cms/blog/{$post->id}")->assertRedirect();

        $this->assertDatabaseMissing('blog_posts', ['id' => $post->id]);
    }

    public function test_a_guest_cannot_reach_the_blog_module_or_a_draft_preview(): void
    {
        $post = $this->article(['status' => 'draft']);

        auth()->logout();

        $this->get('/cms/blog')->assertRedirect('/login');
        $this->get("/cms/blog/{$post->id}/preview")->assertRedirect('/login');
    }

    public function test_a_draft_is_previewable_before_publishing(): void
    {
        $post = $this->article(['status' => 'draft']);

        $this->get("/cms/blog/{$post->id}/preview")->assertOk()->assertInertia(function ($props) {
            $this->assertSame('draft', $props->toArray()['props']['preview']['mode']);
        });
    }

    public function test_the_change_is_recorded_against_the_signed_in_user(): void
    {
        $user = $this->clientAdmin(['name' => 'Daniel Ruiz']);

        $this->actingAs($user)->write()->assertRedirect();

        $post = BlogPost::sole();

        $this->assertSame('Daniel Ruiz', $post->last_updated_by);
        $this->assertSame('Daniel Ruiz', $post->author_name);
    }

    public function test_the_author_name_can_differ_from_who_saved_it(): void
    {
        $user = $this->clientAdmin(['name' => 'Daniel Ruiz']);

        $this->actingAs($user)->write(['author_name' => 'Helen Marsh'])->assertRedirect();

        $post = BlogPost::sole();

        $this->assertSame('Helen Marsh', $post->author_name);
        $this->assertSame('Daniel Ruiz', $post->last_updated_by);
    }

    public function test_a_page_cannot_take_the_blog_namespace(): void
    {
        $page = Page::create([
            'cms_id' => Page::max('cms_id') + 1,
            'slug' => 'somewhere',
            'url' => '/somewhere',
            'title' => 'Somewhere',
            'status' => 'published',
            'seo' => [],
            'published' => [],
        ]);

        $this->patch("/cms/pages/{$page->cms_id}/details", [
            'title' => 'Somewhere',
            'slug' => 'blog/sneaky',
        ])->assertSessionHasErrors('slug');

        $this->assertSame('somewhere', $page->refresh()->slug);

        $this->post('/cms/pages', ['title' => 'Sneaky', 'parent' => 'blog', 'layout' => 'blank'])
            ->assertSessionHasErrors('title');
    }

    public function test_the_listing_page_is_scaffolded_with_a_pulling_section(): void
    {
        $this->artisan('pages:scaffold')->assertSuccessful();

        $page = Page::where('slug', 'blog')->sole();

        $this->assertSame('draft', $page->status);
        $this->assertContains('blog-list', array_column($page->draft, 'type'));
    }

    public function test_an_article_can_be_duplicated_as_a_draft(): void
    {
        $post = $this->article();
        $post->categories()->sync([$this->category()->id]);

        $this->post("/cms/blog/{$post->id}/duplicate")->assertRedirect();

        $copy = BlogPost::where('slug', 'an-article-copy')->sole();

        $this->assertSame('An article (copy)', $copy->title);
        $this->assertSame('draft', $copy->status);
        $this->assertNull($copy->published_at);
        $this->assertCount(1, $copy->categories);
    }

    public function test_the_editor_preview_renders_through_the_same_converter(): void
    {
        $response = $this->post('/cms/blog/render', ['body' => "## Hi\n\n<script>alert(1)</script>"]);

        $html = $response->assertOk()->json('html');

        $this->assertStringContainsString('<h2>Hi</h2>', $html);
        $this->assertStringNotContainsString('<script', $html);
    }
}
