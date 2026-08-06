<?php

namespace Tests\Feature;

use App\Content\ContentLibrary;
use App\Content\Html;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Page;
use App\Models\PageRedirect;
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
            'body' => '<h2>A heading</h2><p>Some <strong>bold</strong> text.</p>',
        ], $body));
    }

    public function test_the_featured_image_carries_a_description_to_the_reader(): void
    {
        $this->write([
            'featured_image' => '/media/2026/08/hero.jpg',
            'featured_image_alt' => 'A couple reading paperwork at a kitchen table',
        ])->assertRedirect();

        $post = BlogPost::sole();

        $this->assertSame('A couple reading paperwork at a kitchen table', $post->featured_image_alt);
        $this->assertSame($post->featured_image_alt, $post->toCard()['imageAlt']);
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

    public function test_every_format_the_scope_asks_for_survives_saving(): void
    {
        $this->write(['body' => '<h2>A heading</h2>'
            .'<p>Some <strong>bold</strong> and <em>italic</em> text with a <a href="https://example.com">link</a>.</p>'
            .'<ul><li>first</li><li>second</li></ul>'
            .'<ol><li>one</li><li>two</li></ol>'
            .'<blockquote><p>A quotation.</p></blockquote>'
            .'<table><thead><tr><th>Column</th></tr></thead><tbody><tr><td>a</td></tr></tbody></table>'
            .'<img src="/media/2026/07/photo.png" alt="A photo">'])->assertRedirect();

        $html = BlogPost::sole()->renderedBody();

        foreach (['<h2>', '<strong>', '<em>', 'href="https://example.com"', '<ul>', '<ol>',
            '<blockquote>', '<table>', '<th>', '<td>', 'src="/media/2026/07/photo.png"'] as $needle) {
            $this->assertStringContainsString($needle, $html, "missing {$needle}");
        }
    }

    /**
     * The editor posts HTML now, so the allowlist is the only thing between a paste and a
     * reader. The words survive; the markup does not.
     */
    public function test_dangerous_markup_is_stripped_on_the_way_in(): void
    {
        $this->write(['body' => '<p>Before</p><script>alert(1)</script>'
            .'<iframe src="https://evil.test"></iframe>'
            .'<p onclick="steal()">Tap me</p>'
            .'<a href="javascript:alert(1)">Bad link</a>'
            .'<img src="x" onerror="alert(1)">'
            .'<form><input name="card"></form>'])->assertRedirect();

        $body = BlogPost::sole()->body;

        foreach (['<script', '<iframe', 'onclick', 'onerror', 'javascript:', '<form', '<input'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $body, "{$forbidden} survived");
        }

        $this->assertStringContainsString('Before', $body);
        $this->assertStringContainsString('Tap me', $body);
    }

    public function test_a_reader_never_receives_unsafe_markup(): void
    {
        $post = $this->article(['body' => Html::clean('<p>Safe</p><script>alert(1)</script>')]);

        $this->get("/blog/{$post->slug}")->assertOk()->assertInertia(function ($props) {
            $body = $props->toArray()['props']['article']['body'];

            $this->assertStringNotContainsString('<script', $body);
            $this->assertStringContainsString('Safe', $body);
        });
    }

    public function test_the_allowlist_keeps_styling_out_of_the_approved_design(): void
    {
        $cleaned = Html::clean('<p style="font-size:80px;color:red">Huge</p><div class="x">Block</div>');

        $this->assertStringNotContainsString('style=', $cleaned);
        $this->assertStringNotContainsString('font-size', $cleaned);
        $this->assertStringContainsString('Huge', $cleaned);
        $this->assertStringContainsString('Block', $cleaned);
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

    public function test_the_agreed_categories_exist(): void
    {
        $this->assertSame(
            ['Property Advice', 'Uncategorised'],
            BlogCategory::whereIn('slug', ['property-advice', 'uncategorised'])
                ->orderBy('name')->pluck('name')->all(),
        );
    }

    public function test_an_article_saved_with_no_category_is_filed_as_uncategorised(): void
    {
        $this->write()->assertRedirect();

        $this->assertSame(['Uncategorised'], BlogPost::sole()->categories->pluck('name')->all());
    }

    public function test_choosing_a_real_category_drops_the_uncategorised_marker(): void
    {
        $this->write()->assertRedirect();

        $post = BlogPost::sole();

        $this->assertTrue($post->categories->first()->isFallback());

        $this->patch("/cms/blog/{$post->id}", [
            'title' => $post->title,
            'categories' => [$this->category('Downsizing')->id],
        ])->assertRedirect();

        $this->assertSame(['Downsizing'], $post->refresh()->categories->pluck('name')->all());
    }

    public function test_clearing_every_category_files_it_back_as_uncategorised(): void
    {
        $downsizing = $this->category('Downsizing');

        $this->write(['categories' => [$downsizing->id]])->assertRedirect();

        $post = BlogPost::sole();

        $this->patch("/cms/blog/{$post->id}", ['title' => $post->title, 'categories' => []])
            ->assertRedirect();

        $this->assertSame(['Uncategorised'], $post->refresh()->categories->pluck('name')->all());
    }

    /**
     * Saving must not depend on a row an editor is free to delete.
     */
    public function test_the_fallback_returns_after_being_deleted(): void
    {
        BlogCategory::where('slug', 'uncategorised')->delete();

        $this->write()->assertRedirect();

        $this->assertSame(['Uncategorised'], BlogPost::sole()->categories->pluck('name')->all());
    }

    public function test_deleting_a_category_refiles_its_articles_rather_than_orphaning_them(): void
    {
        $downsizing = $this->category('Downsizing');
        $finance = $this->category('Finance');

        $only = $this->article(['slug' => 'only-downsizing']);
        $only->syncCategories([$downsizing->id]);

        $both = $this->article(['slug' => 'in-both']);
        $both->syncCategories([$downsizing->id, $finance->id]);

        $downsizing->delete();

        /* Left with nothing, so it falls back. */
        $this->assertSame(['Uncategorised'], $only->refresh()->categories->pluck('name')->all());

        /* Still has Finance, so it is not touched. */
        $this->assertSame(['Finance'], $both->refresh()->categories->pluck('name')->all());
    }

    public function test_a_super_administrator_can_delete_a_category_from_the_cms(): void
    {
        $downsizing = $this->category('Downsizing');
        $post = $this->article();
        $post->syncCategories([$downsizing->id]);

        $this->delete("/cms/blog-categories/{$downsizing->id}")->assertRedirect();

        $this->assertDatabaseMissing('blog_categories', ['id' => $downsizing->id]);

        /* The article survives and is re-filed rather than orphaned. */
        $this->assertSame(['Uncategorised'], $post->refresh()->categories->pluck('name')->all());
        $this->assertDatabaseHas('blog_posts', ['id' => $post->id]);
    }

    public function test_a_client_administrator_cannot_delete_a_category(): void
    {
        $downsizing = $this->category('Downsizing');

        $this->actingAs($this->clientAdmin());

        $this->delete("/cms/blog-categories/{$downsizing->id}")->assertForbidden();

        $this->assertDatabaseHas('blog_categories', ['id' => $downsizing->id]);
    }

    public function test_the_uncategorised_category_cannot_be_deleted(): void
    {
        $fallback = BlogCategory::where('slug', 'uncategorised')->sole();

        $this->delete("/cms/blog-categories/{$fallback->id}")->assertStatus(422);

        $this->assertDatabaseHas('blog_categories', ['id' => $fallback->id]);
    }

    public function test_uncategorised_is_never_shown_to_readers(): void
    {
        $post = $this->article();
        $post->syncCategories([]);

        $library = (new ContentLibrary)->posts();

        $this->assertNotContains('uncategorised', array_column($library['postCategories'], 'slug'));

        /* Still reachable — it is the label that is hidden, not the article. */
        $this->assertCount(1, $library['posts']);

        /* And it must not surface as a tag on the card or the article page. */
        $this->assertNull($library['posts'][0]['category']);
        $this->assertSame([], $library['posts'][0]['categories']);
        $this->assertNull($post->refresh()->toArticle()['category']);
    }

    public function test_categories_can_be_created_reordered_and_disabled(): void
    {
        $this->post('/cms/blog-categories', ['name' => 'Downsizing'])->assertRedirect();
        $this->post('/cms/blog-categories', ['name' => 'Finance'])->assertRedirect();

        $first = BlogCategory::where('name', 'Downsizing')->sole();
        $second = BlogCategory::where('name', 'Finance')->sole();

        $this->post('/cms/blog-categories/reorder', ['ids' => [$second->id, $first->id]])->assertRedirect();

        $this->assertSame(
            ['Finance', 'Downsizing'],
            BlogCategory::ordered()->whereIn('name', ['Downsizing', 'Finance'])->pluck('name')->all(),
        );

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

        $this->assertSame(['Finance'], array_column($library['postCategories'], 'name'));
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

    public function test_a_reader_can_filter_the_listing_by_category(): void
    {
        $downsizing = $this->category('Downsizing');
        $finance = $this->category('Finance');

        $this->article(['slug' => 'about-downsizing'])->categories()->sync([$downsizing->id]);
        $this->article(['slug' => 'about-money'])->categories()->sync([$finance->id]);

        $library = new ContentLibrary;

        $this->assertCount(2, $library->posts()['posts']);
        $this->assertSame(['about-downsizing'], array_column($library->posts(1, 'downsizing')['posts'], 'slug'));
        $this->assertSame('downsizing', $library->posts(1, 'downsizing')['postCategory']);

        $this->assertSame(
            ['Downsizing', 'Finance'],
            array_column($library->posts()['postCategories'], 'name'),
        );
    }

    public function test_the_filter_reaches_the_listing_page_from_the_address(): void
    {
        $downsizing = $this->category('Downsizing');
        $finance = $this->category('Finance');

        $this->article(['slug' => 'about-downsizing'])->categories()->sync([$downsizing->id]);
        $this->article(['slug' => 'about-money'])->categories()->sync([$finance->id]);

        $this->artisan('pages:scaffold')->assertSuccessful();

        $page = Page::where('slug', 'blog')->sole();
        $page->forceFill(['status' => 'published', 'published' => $page->draft, 'draft' => null])->save();

        auth()->logout();

        $this->get('/blog?category=finance')->assertOk()->assertInertia(function ($props) {
            $library = $props->toArray()['props']['library'];

            $this->assertSame('finance', $library['postCategory']);
            $this->assertSame(['about-money'], array_column($library['posts'], 'slug'));
        });
    }

    public function test_an_empty_or_unknown_category_is_ignored_rather_than_erroring(): void
    {
        $this->article()->categories()->sync([$this->category()->id]);

        $this->assertCount(0, (new ContentLibrary)->posts(1, 'no-such-category')['posts']);
        $this->assertCount(1, (new ContentLibrary)->posts(1, null)['posts']);

        $this->get('/blog/articles?category=not%20a%20slug')->assertOk()->assertJsonPath('postCategory', null);
    }

    public function test_a_disabled_category_cannot_be_filtered_to(): void
    {
        $hidden = $this->category('Retired topic', active: false);

        $this->article()->categories()->sync([$hidden->id]);

        $this->assertCount(0, (new ContentLibrary)->posts(1, 'retired-topic')['posts']);
    }

    public function test_load_more_stays_inside_the_chosen_category(): void
    {
        $downsizing = $this->category('Downsizing');
        $finance = $this->category('Finance');

        foreach (range(1, ContentLibrary::PER_PAGE + 2) as $n) {
            $this->article(['slug' => "downsizing-{$n}", 'published_at' => now()->subDays($n)])
                ->categories()->sync([$downsizing->id]);
        }

        $this->article(['slug' => 'money-one'])->categories()->sync([$finance->id]);

        $second = $this->get('/blog/articles?page=2&category=downsizing')->assertOk()->json();

        $this->assertCount(2, $second['posts']);
        $this->assertNotContains('money-one', array_column($second['posts'], 'slug'));
        $this->assertFalse($second['hasMorePosts']);
    }

    public function test_renaming_a_published_article_keeps_the_old_address_working(): void
    {
        $post = $this->article(['slug' => 'old-name']);

        $this->patch("/cms/blog/{$post->id}", [
            'title' => $post->title,
            'slug' => 'new-name',
        ])->assertRedirect();

        $this->assertSame('new-name', $post->refresh()->slug);

        $this->get('/blog/old-name')->assertRedirect('/blog/new-name')->assertStatus(301);
        $this->get('/blog/new-name')->assertOk();
    }

    public function test_renaming_a_draft_article_leaves_no_redirect(): void
    {
        $post = $this->article(['slug' => 'old-name', 'status' => 'draft']);

        $this->patch("/cms/blog/{$post->id}", ['title' => $post->title, 'slug' => 'new-name'])
            ->assertRedirect();

        $this->assertSame(0, PageRedirect::count());
    }

    public function test_moving_an_article_twice_collapses_the_chain(): void
    {
        $post = $this->article(['slug' => 'a']);

        $this->patch("/cms/blog/{$post->id}", ['title' => $post->title, 'slug' => 'b'])->assertRedirect();
        $this->patch("/cms/blog/{$post->id}", ['title' => $post->title, 'slug' => 'c'])->assertRedirect();

        $this->assertSame(
            ['/blog/a' => '/blog/c', '/blog/b' => '/blog/c'],
            PageRedirect::pluck('to_url', 'from_url')->all(),
        );
        $this->get('/blog/a')->assertRedirect('/blog/c');
    }

    public function test_moving_an_article_back_removes_the_redirect_that_would_shadow_it(): void
    {
        $post = $this->article(['slug' => 'a']);

        $this->patch("/cms/blog/{$post->id}", ['title' => $post->title, 'slug' => 'b'])->assertRedirect();
        $this->patch("/cms/blog/{$post->id}", ['title' => $post->title, 'slug' => 'a'])->assertRedirect();

        $this->assertSame(0, PageRedirect::where('from_url', '/blog/a')->count());
        $this->get('/blog/a')->assertOk();
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

        /* Recoverable, not gone: it leaves the CMS lists and the website, and waits under
           Recently deleted until somebody removes it for good. */
        $this->assertSoftDeleted('blog_posts', ['id' => $post->id]);
        $this->assertSame(0, BlogPost::count());
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

    /**
     * The listing page ships from the seed, published and already pulling. It used to be an
     * empty draft the scaffold created, which is why /blog was one of two menu links that 404ed
     * on a fresh install.
     */
    public function test_the_listing_page_ships_published_with_a_pulling_section(): void
    {
        $page = Page::where('slug', 'blog')->sole();

        $this->assertSame('published', $page->status);
        $this->assertContains('blog-list', array_column($page->published, 'type'));

        $this->artisan('pages:scaffold')->assertSuccessful();

        $this->assertSame('published', $page->refresh()->status, 'the scaffold overwrote the seeded page');
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
}
