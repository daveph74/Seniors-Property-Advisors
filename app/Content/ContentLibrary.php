<?php

namespace App\Content;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\Testimonial;

class ContentLibrary
{
    public const PER_PAGE = 12;

    public function for(?string $slug = null, ?string $category = null): array
    {
        return [
            'faqs' => $this->faqs($slug),
            'faqCategories' => $this->faqCategories($slug),
            'testimonials' => $this->testimonials(),
        ] + $this->posts(1, $category);
    }

    /**
     * The groupings a reader can filter by: only categories that are switched on and actually
     * have a question showing. A disabled category drops off the filters while its questions
     * stay answerable under "all" — hiding a grouping should not hide the answers, the same rule
     * the blog follows for Uncategorised.
     */
    private function faqCategories(?string $slug): array
    {
        return FaqCategory::query()
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->whereHas('faqs', fn ($query) => $query
                ->where('active', true)
                ->where(fn ($q) => $q->whereNull('page_slug')->orWhere('page_slug', $slug)))
            ->pluck('name')
            ->all();
    }

    /**
     * Every switched-on category, for the builder's category pickers. Deliberately wider than what
     * the reader-facing lists offer: those hide a grouping with nothing behind it, but an editor
     * setting a section up needs to reach a category before it has been filled. Builder-only, so
     * it never rides along with a public page render.
     */
    /**
     * Every testimonial a reader may see, in the order the library sets. Whole rather than paged
     * and unfiltered by featured or limit: those are decisions a section makes, and the set is
     * small enough that sending it once beats a request per section. `active` already requires
     * recorded consent, so an unconfirmed testimonial cannot leave the CMS by this route.
     */
    private function testimonials(): array
    {
        return Testimonial::active()->ordered()->get()->map->toCard()->all();
    }

    public function choices(): array
    {
        return [
            'testimonialChoices' => Testimonial::ordered()->get()
                ->map(fn (Testimonial $t) => [
                    'id' => (string) $t->id,
                    'label' => $t->name.($t->location ? ' — '.$t->location : ''),
                ])
                ->all(),
            'allFaqCategories' => FaqCategory::where('active', true)
                ->orderBy('sort_order')->orderBy('id')->pluck('name')->all(),
            'allPostCategories' => BlogCategory::active()->ordered()
                ->get(['name', 'slug'])
                ->map(fn (BlogCategory $c) => ['name' => $c->name, 'slug' => $c->slug])
                ->all(),
        ];
    }

    /**
     * One page of published articles, newest first. Capped because this ships with every
     * page render; the load-more route asks for later pages.
     *
     * Filtering happens here rather than in the browser for two reasons: a filtered listing is a
     * real address a reader can share or bookmark, and it pages correctly — a client-side filter
     * would only ever sift the batch already loaded, so with more than PER_PAGE articles it would
     * quietly hide the matching ones sitting on later pages and look like it had worked.
     *
     * It does not, as this comment used to claim, make the listing work with JavaScript off.
     * There is no server-side rendering here, so a reader without JavaScript gets an empty page
     * whatever this method does. Shipping every article instead would trade the paging problem
     * for a worse one, since the library rides along with every page render, not just the blog.
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
