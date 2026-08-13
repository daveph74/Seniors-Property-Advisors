<?php

namespace App\Models;

use App\Enquiries\FindMyAgentOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Enquiry extends Model
{
    use HasFactory;

    public const NEW = 'new';

    public const IN_PROGRESS = 'in_progress';

    public const DEALT_WITH = 'dealt_with';

    /** @var array<string, string> the wording an editor sees, in the order the work moves */
    public const STATUSES = [
        self::NEW => 'New',
        self::IN_PROGRESS => 'In progress',
        self::DEALT_WITH => 'Dealt with',
    ];

    public const CONTACT_FORM = 'contact_form';

    public const FIND_MY_AGENT = 'find_my_agent';

    /**
     * Which form this came through.
     *
     * Its own column rather than a reading of `page_slug`: that records the address the form sat on,
     * which is a *where* and not a *what* — both forms can be sent from `/contact`, and the value
     * arrives from the browser. An inbox that filters by source needs a fact, not an inference.
     *
     * @var array<string, string>
     */
    public const SOURCES = [
        self::CONTACT_FORM => 'Contact form',
        self::FIND_MY_AGENT => 'Find My Agent',
    ];

    protected $table = 'enquiries';

    protected $fillable = [
        'name', 'email', 'phone', 'suburb', 'message', 'consented', 'page_slug',
        'source', 'details', 'status', 'status_changed_at', 'read_at',
    ];

    protected $casts = [
        'consented' => 'boolean',
        'details' => 'array',
        'status_changed_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    /* The columns default to these too, but a default only the database knows means a freshly
       created instance reports no status or source at all until something reloads it. */
    protected $attributes = ['status' => self::NEW, 'source' => self::CONTACT_FORM];

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

    /** Narrows to one form. `all` is not a source, so it narrows nothing. */
    public function scopeFromSource(Builder $query, ?string $source): Builder
    {
        return $query->when(
            isset(self::SOURCES[(string) $source]),
            fn (Builder $q) => $q->where('source', $source),
        );
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function sourceLabel(): string
    {
        return self::SOURCES[$this->source] ?? $this->source;
    }

    /**
     * What the sender is told to quote if they ring before anybody has called them back.
     *
     * Derived, never stored: there is no second copy to keep in step, no uniqueness to enforce, and
     * the number leads straight back to a row this CMS can open. The year is the row's own, so a
     * reference stays the same next January.
     */
    public function reference(): string
    {
        return sprintf('AF-%s-%05d', ($this->created_at ?? now())->format('Y'), $this->id);
    }

    /** The answers they picked from a list, ready to print. Empty for a contact-form enquiry. */
    public function answers(): array
    {
        return FindMyAgentOptions::describe($this->details);
    }
}
