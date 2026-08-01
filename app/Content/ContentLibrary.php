<?php

namespace App\Content;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Faq;

class ContentLibrary
{
    public const PER_PAGE = 12;

    public function for(?string $slug = null, ?string $category = null): array
    {
        return ['faqs' => $this->faqs($slug)] + $this->posts(1, $category);
    }

    /**
     * One page of published articles, newest first. Capped because this ships with every
     * page render; the load-more route asks for later pages.
     *
     * Filtering happens here rather than in the browser so that a filtered listing is a real
     * address a reader can share or bookmark, works with JavaScript off, and pages correctly —
     * a client-side filter would only ever sift the batch already loaded.
     */
    public function posts(int $page = 1, ?string $category = null): array
    {
        $query = fn () => BlogPost::published()
            ->when($category, fn ($q) => $q->whereHas(
                'categories',
                fn ($c) => $c->where('blog_categories.slug', $category)->where('blog_categories.active', true),
            ));

        $total = $query()->count();
        $posts = $query()
            ->ordered()
            ->with('categories')
            ->skip(($page - 1) * self::PER_PAGE)
            ->take(self::PER_PAGE)
            ->get();

        return [
            'posts' => $posts->map->toCard()->all(),
            'page' => $page,
            'hasMorePosts' => $total > $page * self::PER_PAGE,
            'postCategories' => $this->postCategories(),
            'postCategory' => $category,
        ];
    }

    /**
     * Articles sharing a category, never the article itself. "Where practical" in §5, so
     * it quietly returns nothing when an article has no categories.
     */
    public function related(BlogPost $post, int $limit = 3): array
    {
        $categoryIds = $post->categories->pluck('id');

        if ($categoryIds->isEmpty()) {
            return [];
        }

        return BlogPost::published()
            ->ordered()
            ->with('categories')
            ->whereKeyNot($post->getKey())
            ->whereHas('categories', fn ($query) => $query->whereIn('blog_categories.id', $categoryIds))
            ->take($limit)
            ->get()
            ->map
            ->toCard()
            ->all();
    }

    /**
     * Only categories that actually have something published behind them — an empty filter a
     * reader can click is worse than no filter. Uncategorised is left out too: it is
     * housekeeping for the editor, not a topic anyone would choose. Those articles still show
     * under "All articles".
     */
    private function postCategories(): array
    {
        return BlogCategory::active()
            ->ordered()
            ->where('slug', '<>', BlogCategory::UNCATEGORISED)
            ->whereHas('posts', fn ($query) => $query->where('status', 'published'))
            ->get(['name', 'slug'])
            ->map(fn (BlogCategory $category) => ['name' => $category->name, 'slug' => $category->slug])
            ->all();
    }

    private function faqs(?string $slug): array
    {
        return Faq::query()
            ->active()
            ->ordered()
            ->with('category')
            ->where(fn ($q) => $q->whereNull('page_slug')->orWhere('page_slug', $slug))
            ->get()
            ->map(fn (Faq $faq) => [
                'id' => $faq->id,
                'question' => $faq->question,
                'answer' => $faq->answer,
                'category' => $faq->category?->name,
                'pageSlug' => $faq->page_slug,
            ])
            ->all();
    }
}
