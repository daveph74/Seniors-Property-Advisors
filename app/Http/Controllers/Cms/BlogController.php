<?php

namespace App\Http\Controllers\Cms;

use App\Content\PageContentStore;
use App\Http\Controllers\BlogController as PublicBlogController;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveBlogCategoryRequest;
use App\Http\Requests\SaveBlogPostRequest;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\PageRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BlogController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Cms/Blog/Index', [
            'articles' => BlogPost::with('categories')->ordered()->get()->map->toAdminRow()->all(),
            'categories' => $this->categories(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Cms/Blog/Editor', [
            'article' => null,
            'categories' => $this->categories(),
            'defaultAuthor' => request()->user()->name,
        ]);
    }

    public function edit(BlogPost $post): Response
    {
        return Inertia::render('Cms/Blog/Editor', [
            'article' => $post->load('categories')->toAdminRow() + [
                'body' => $post->body,
                'seo' => $post->seo ?? [],
            ],
            'categories' => $this->categories(),
            'defaultAuthor' => request()->user()->name,
        ]);
    }

    public function store(SaveBlogPostRequest $request): RedirectResponse
    {
        $details = $request->details();

        $post = BlogPost::create([
            ...$details,
            'slug' => $this->availableSlug($request->slug() ?? Str::slug($details['title'])),
            'status' => $request->status() ?? 'draft',
            'author_name' => $details['author_name'] ?? $request->user()->name,
            'last_updated_by' => $request->user()->name,
        ]);

        $post->syncCategories($request->categories() ?? []);

        return redirect()->route('cms.blog.edit', $post);
    }

    public function update(SaveBlogPostRequest $request, BlogPost $post): RedirectResponse
    {
        $changes = $request->details() + ['last_updated_by' => $request->user()->name];
        $slug = $request->slug();

        if ($slug !== null && $slug !== $post->slug) {
            $changes['slug'] = $this->availableSlug($slug, $post->id);

            $this->keepOldAddressWorking($post, $changes['slug']);
        }

        if ($request->status() !== null) {
            $changes['status'] = $request->status();
        }

        $post->forceFill($changes)->save();

        if ($request->categories() !== null) {
            $post->syncCategories($request->categories());
        }

        return back();
    }

    public function publish(BlogPost $post): RedirectResponse
    {
        $post->forceFill([
            'status' => 'published',
            'published_at' => $post->published_at ?? now(),
            'last_updated_by' => request()->user()->name,
        ])->save();

        return back();
    }

    public function unpublish(BlogPost $post): RedirectResponse
    {
        return $this->setStatus($post, 'draft');
    }

    public function archive(BlogPost $post): RedirectResponse
    {
        return $this->setStatus($post, 'archived');
    }

    public function unarchive(BlogPost $post): RedirectResponse
    {
        return $this->setStatus($post, $post->published_at === null ? 'draft' : 'published');
    }

    public function duplicate(BlogPost $post): RedirectResponse
    {
        $copy = BlogPost::create($post->only([
            'summary', 'body', 'featured_image', 'author_name', 'seo',
        ]) + [
            'title' => "{$post->title} (copy)",
            'slug' => $this->availableSlug($post->slug.'-copy'),
            'status' => 'draft',
            'published_at' => null,
            'last_updated_by' => request()->user()->name,
        ]);

        $copy->syncCategories($post->categories->pluck('id')->all());

        return redirect()->route('cms.blog.edit', $copy);
    }

    public function destroy(BlogPost $post): RedirectResponse
    {
        $post->delete();

        return redirect()->route('cms.blog.index');
    }

    public function preview(BlogPost $post): Response
    {
        $store = app(PageContentStore::class);

        return Inertia::render('Article', [
            'article' => $post->load('categories')->toArticle(),
            'seo' => PublicBlogController::sharing($post),
            'related' => [],
            'globals' => $store->globals(),
            'preview' => [
                'mode' => $post->status === 'published' ? 'published' : 'draft',
                'editUrl' => route('cms.blog.edit', $post),
            ],
        ]);
    }

    public function storeCategory(SaveBlogCategoryRequest $request): RedirectResponse
    {
        BlogCategory::create([
            'name' => $request->name(),
            'slug' => $this->availableCategorySlug(Str::slug($request->name())),
            'sort_order' => (int) BlogCategory::max('sort_order') + 1,
            'active' => $request->active(),
        ]);

        return back();
    }

    public function updateCategory(SaveBlogCategoryRequest $request, BlogCategory $category): RedirectResponse
    {
        $category->forceFill(['name' => $request->name(), 'active' => $request->active()])->save();

        return back();
    }

    public function reorderCategories(): RedirectResponse
    {
        foreach ((array) request()->input('ids', []) as $position => $id) {
            BlogCategory::where('id', (int) $id)->update(['sort_order' => $position + 1]);
        }

        return back();
    }

    /**
     * Renaming a published article would otherwise kill every link already shared to it. The
     * same `page_redirects` table pages use serves this, with the two traps it already
     * handles: chains are collapsed so a→b→c leaves a→c rather than stacking hops, and a row
     * that would shadow the new address is removed in case an article moves back.
     *
     * Drafts are skipped — an unpublished article has no address worth preserving.
     */
    private function keepOldAddressWorking(BlogPost $post, string $newSlug): void
    {
        $from = "/blog/{$post->slug}";
        $to = "/blog/{$newSlug}";

        if ($post->status === 'published') {
            PageRedirect::where('to_url', $from)->update(['to_url' => $to]);
            PageRedirect::updateOrCreate(['from_url' => $from], ['to_url' => $to]);
        }

        PageRedirect::where('from_url', $to)->delete();
    }

    private function setStatus(BlogPost $post, string $status): RedirectResponse
    {
        $post->forceFill(['status' => $status, 'last_updated_by' => request()->user()->name])->save();

        return back();
    }

    private function categories(): array
    {
        return BlogCategory::ordered()->withCount('posts')->get()->map(fn (BlogCategory $category) => [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'active' => $category->active,
            'count' => $category->posts_count,
        ])->all();
    }

    private function availableSlug(string $base, ?int $ignore = null): string
    {
        $base = Str::slug($base) ?: 'article';
        $candidate = $base;
        $n = 1;

        while (in_array($candidate, BlogPost::RESERVED_SLUGS, true)
            || BlogPost::where('slug', $candidate)->when($ignore, fn ($q) => $q->whereKeyNot($ignore))->exists()) {
            $n++;
            $candidate = "{$base}-{$n}";
        }

        return $candidate;
    }

    private function availableCategorySlug(string $base): string
    {
        $base = $base ?: 'category';
        $candidate = $base;
        $n = 1;

        while (BlogCategory::where('slug', $candidate)->exists()) {
            $n++;
            $candidate = "{$base}-{$n}";
        }

        return $candidate;
    }
}
