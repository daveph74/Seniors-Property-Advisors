<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BlogCategory extends Model
{
    /**
     * The home for articles with nothing else chosen. Held as a slug rather than a name so
     * renaming it in the CMS cannot detach every article that relies on it.
     */
    public const UNCATEGORISED = 'uncategorised';

    protected $fillable = ['name', 'slug', 'sort_order', 'active'];

    protected $casts = [
        'sort_order' => 'integer',
        'active' => 'boolean',
    ];

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(BlogPost::class, 'blog_category_post');
    }

    /**
     * The pivot cascades on delete, so removing a category would otherwise leave its articles
     * with none at all — the state the whole Uncategorised rule exists to prevent. Re-filing
     * happens here rather than in a controller so it holds however the category goes: the CMS,
     * a console command or a migration.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $category) {
            if ($category->isFallback()) {
                return;
            }

            $orphans = $category->posts()
                ->whereDoesntHave('categories', fn ($query) => $query->whereKeyNot($category->getKey()))
                ->get();

            if ($orphans->isEmpty()) {
                return;
            }

            $fallback = self::fallback();

            $orphans->each(fn (BlogPost $post) => $post->categories()->syncWithoutDetaching([$fallback->id]));
        });
    }

    public static function fallback(): self
    {
        return self::firstOrCreate(
            ['slug' => self::UNCATEGORISED],
            ['name' => 'Uncategorised', 'sort_order' => 999, 'active' => true],
        );
    }

    public function isFallback(): bool
    {
        return $this->slug === self::UNCATEGORISED;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
