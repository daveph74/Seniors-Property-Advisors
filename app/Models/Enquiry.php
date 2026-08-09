<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Enquiry extends Model
{
    public const NEW = 'new';

    public const IN_PROGRESS = 'in_progress';

    public const DEALT_WITH = 'dealt_with';

    /** @var array<string, string> the wording an editor sees, in the order the work moves */
    public const STATUSES = [
        self::NEW => 'New',
        self::IN_PROGRESS => 'In progress',
        self::DEALT_WITH => 'Dealt with',
    ];

    protected $table = 'enquiries';

    protected $fillable = [
        'name', 'email', 'phone', 'suburb', 'message', 'consented', 'page_slug',
        'status', 'status_changed_at', 'read_at',
    ];

    protected $casts = [
        'consented' => 'boolean',
        'status_changed_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    /* The column defaults to this too, but a default only the database knows means a freshly
       created instance reports no status at all until something reloads it. */
    protected $attributes = ['status' => self::NEW];

    /**
     * Still waiting on somebody. This is the count the sidebar and the dashboard report, and it
     * falls only when the work is done — reading an enquiry does not move it.
     */
    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->where('status', '!=', self::DEALT_WITH);
    }

    /** Arrived and nobody has opened it. The header's counter, and nothing else. */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
