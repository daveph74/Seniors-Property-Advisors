<?php

namespace App\Models;

use App\Content\Html;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BlogPost extends Model
{
    public const STATUSES = ['draft', 'published', 'archived'];

    /**
     * /blog/articles is the load-more route, declared before /blog/{article}, so an
     * article may not take that slug or it would be unreachable.
     */
    public const RESERVED_SLUGS = ['articles'];

    protected $fillable = [
        'slug', 'title', 'summary', 'body', 'featured_image', 'featured_image_alt',
        'author_name', 'status', 'published_at', 'seo', 'last_updated_by',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'seo' => 'array',
    ];

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(BlogCategory::class, 'blog_category_post');
    }

    /**
     * An article always belongs somewhere. With nothing chosen it falls to Uncategorised, and
     * the moment a real category is picked that placeholder is dropped again — otherwise
     * articles would quietly accumulate both.
     *
     * The category is fetched by slug and created if missing, so deleting it in the CMS cannot
     * break saving; it simply reappears the next time it is needed.
     */
    public function syncCategories(array $ids): void
    {
        $real = BlogCategory::whereKey($ids)
            ->where('slug', '<>', BlogCategory::UNCATEGORISED)
            ->pluck('id')
            ->all();

        $this->categories()->sync($real === [] ? [BlogCategory::fallback()->id] : $real);
    }

    /**
     * Scheduled publishing is out of scope (§17), so this deliberately ignores
     * published_at — the date is what readers see, not a release trigger.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByRaw('COALESCE(published_at, updated_at) DESC')->orderByDesc('id');
    }

    public function url(): string
    {
        return '/blog/'.$this->slug;
    }

    /**
     * Bodies are purified before they are stored, so this is a pass-through. It stays a method
     * rather than reading the column directly so there is one place to change if that ever
     * stops being true.
     */
    public function renderedBody(): string
    {
        return (string) $this->body;
    }

    public function cardSummary(): string
    {
        return trim((string) $this->summary) !== ''
            ? (string) $this->summary
            : Html::excerpt($this->body);
    }

    public function toCard(): array
    {
        /* Uncategorised is filing, not a topic — a reader should never see it on a card. */
        $shown = $this->categories->reject(fn (BlogCategory $category) => $category->isFallback());

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'url' => $this->url(),
            'summary' => $this->cardSummary(),
            'image' => $this->featured_image,
            'imageAlt' => $this->featured_image_alt,
            'date' => $this->published_at?->format('j F Y'),
            'category' => $shown->first()?->name,
            'categories' => $shown->pluck('name')->values()->all(),
        ];
    }

    public function toArticle(): array
    {
        return $this->toCard() + [
            'body' => $this->renderedBody(),
            'author' => $this->author_name,
            'seo' => $this->seo ?? [],
        ];
    }

    public function toAdminRow(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'url' => $this->url(),
            'summary' => $this->summary,
            'image' => $this->featured_image,
            'imageAlt' => $this->featured_image_alt,
            'author' => $this->author_name,
            'status' => $this->status,
            'publishedAt' => $this->published_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
            'by' => $this->last_updated_by,
            'categoryIds' => $this->categories->pluck('id')->all(),
            'categories' => $this->categories->pluck('name')->all(),
        ];
    }
}
