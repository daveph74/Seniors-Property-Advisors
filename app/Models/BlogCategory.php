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
