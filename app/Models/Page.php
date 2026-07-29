<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Page extends Model
{
    protected $fillable = [
        'cms_id',
        'slug',
        'url',
        'title',
        'status',
        'schema_version',
        'seo',
        'draft',
        'published',
        'last_updated_by',
        'published_by',
        'published_at',
    ];

    protected $casts = [
        'cms_id' => 'integer',
        'schema_version' => 'integer',
        'seo' => 'array',
        'draft' => 'array',
        'published' => 'array',
        'published_at' => 'datetime',
    ];

    public function revisions(): HasMany
    {
        return $this->hasMany(PageRevision::class)->orderByDesc('n');
    }

    public function toDocument(): array
    {
        return [
            'slug' => $this->slug,
            'url' => $this->url,
            'cmsId' => $this->cms_id,
            'title' => $this->title,
            'status' => $this->status,
            'seo' => $this->seo ?? [],
            'draft' => $this->draft,
            'published' => $this->published ?? [],
            'last_updated_by' => $this->last_updated_by,
            'updated_at' => $this->updated_at?->toIso8601String(),
            'published_by' => $this->published_by,
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}
