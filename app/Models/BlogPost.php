<?php

namespace App\Models;

use App\Content\Markdown;
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
        'slug', 'title', 'summary', 'body', 'featured_image',
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

    public function renderedBody(): string
    {
        return Markdown::toHtml($this->body);
    }

    public function cardSummary(): string
    {
        return trim((string) $this->summary) !== ''
            ? (string) $this->summary
            : Markdown::excerpt($this->body);
    }

    public function toCard(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'url' => $this->url(),
            'summary' => $this->cardSummary(),
            'image' => $this->featured_image,
            'date' => $this->published_at?->format('j F Y'),
            'category' => $this->categories->first()?->name,
            'categories' => $this->categories->pluck('name')->all(),
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
