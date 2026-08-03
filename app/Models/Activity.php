<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class Activity extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'activity_log';

    protected $fillable = ['action', 'subject_type', 'subject_id', 'subject_label', 'by_id', 'by_name'];

    /**
     * Records one action. Called from the observer for the ordinary cases, and directly where the
     * verb cannot be read off a model — publishing a page happens in the content store, not on the
     * row.
     */
    public static function record(string $action, Model $subject, ?string $label = null): void
    {
        $user = Auth::user();

        static::create([
            'action' => $action,
            'subject_type' => class_basename($subject),
            'subject_id' => $subject->getKey(),
            'subject_label' => Str::limit($label ?? static::labelFor($subject), 180, ''),
            'by_id' => $user?->id,
            /* Kept as text as well: an entry has to stay readable after the account is gone. */
            'by_name' => $user?->name,
        ]);
    }

    /**
     * Records something that is not a content row. The header and footer menus and the site-wide
     * wording both live in one `settings` row keyed by a string, so there is no numeric id to point
     * at and "edited Setting #globals" would say nothing — the label carries the meaning instead.
     */
    public static function note(string $action, string $type, string $label): void
    {
        $user = Auth::user();

        static::create([
            'action' => $action,
            'subject_type' => $type,
            'subject_id' => null,
            'subject_label' => Str::limit($label, 180, ''),
            'by_id' => $user?->id,
            'by_name' => $user?->name,
        ]);
    }

    public static function labelFor(Model $subject): string
    {
        foreach (['title', 'question', 'name'] as $field) {
            if (filled($subject->$field ?? null)) {
                return (string) $subject->$field;
            }
        }

        return class_basename($subject).' #'.$subject->getKey();
    }

    public function subjectLabel(): string
    {
        return $this->subject_label ?: $this->subject_type;
    }
}
