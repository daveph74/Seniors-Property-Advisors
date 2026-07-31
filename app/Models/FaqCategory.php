<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FaqCategory extends Model
{
    protected $fillable = ['name', 'sort_order', 'active'];

    protected $casts = [
        'sort_order' => 'integer',
        'active' => 'boolean',
    ];

    public function faqs(): HasMany
    {
        return $this->hasMany(Faq::class);
    }
}
