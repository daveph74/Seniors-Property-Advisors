<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageRevision extends Model
{
    protected $fillable = [
        'page_id',
        'n',
        'action',
        'by',
        'sections',
        'schema_version',
    ];

    protected $casts = [
        'n' => 'integer',
        'schema_version' => 'integer',
        'sections' => 'array',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function toSummaryRow(): array
    {
        return [
            'n' => $this->n,
            'action' => $this->action,
            'by' => $this->by,
            'at' => $this->created_at?->toIso8601String(),
            'sections' => $this->sections ?? [],
        ];
    }
}
