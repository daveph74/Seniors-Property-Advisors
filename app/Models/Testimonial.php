<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Testimonial extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'quote', 'location', 'headline', 'image', 'image_alt', 'rating',
        'sort_order', 'featured', 'active',
        'consent_confirmed_at', 'consent_confirmed_by', 'last_updated_by',
    ];

    protected $casts = [
        'rating' => 'integer',
        'sort_order' => 'integer',
        'featured' => 'boolean',
        'active' => 'boolean',
        'consent_confirmed_at' => 'datetime',
    ];

    /**
     * Nothing reaches a reader without recorded permission. Both flags are guarded rather than
     * only `active`, since a featured testimonial is the most visible kind there is.
     */
    public function hasConsent(): bool
    {
        return $this->consent_confirmed_at !== null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true)->whereNotNull('consent_confirmed_at');
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('featured', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function toCard(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'quote' => $this->quote,
            'location' => $this->location,
            'headline' => $this->headline,
            'avatar' => $this->image,
            'avatarAlt' => $this->image_alt,
            'rating' => $this->rating,
            'featured' => $this->featured,
        ];
    }
}
