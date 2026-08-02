<?php

namespace App\Observers;

use App\Models\Activity;
use Illuminate\Database\Eloquent\Model;

/**
 * Watches the content models so the log cannot be forgotten.
 *
 * Recording from each controller was the alternative, and it only stays complete while everybody
 * remembers — a new route, a tinker session or a command would slip past. This sits on the model,
 * so anything that saves is recorded.
 *
 * Publishing, unpublishing and archiving are not separate events to the database: they are a status
 * column changing. The verb is read back off that change rather than being passed in, which keeps
 * the log honest about what actually happened to the row.
 */
class RecordsActivity
{
    private const FROM_STATUS = [
        'published' => 'published',
        'draft' => 'unpublished',
        'archived' => 'archived',
    ];

    public function created(Model $model): void
    {
        Activity::record('created', $model);
    }

    public function updated(Model $model): void
    {
        Activity::record($this->verbFor($model), $model);
    }

    public function deleted(Model $model): void
    {
        Activity::record('deleted', $model);
    }

    private function verbFor(Model $model): string
    {
        if ($model->wasChanged('status')) {
            $was = $model->getOriginal('status');

            if ($was === 'archived' && $model->status !== 'archived') {
                return 'restored';
            }

            return self::FROM_STATUS[$model->status] ?? 'edited';
        }

        /* Questions and testimonials have no status — showing and hiding is an `active` flag, and
           §13 asks for published and unpublished, not for "edited" three times in a row. */
        if ($model->wasChanged('active')) {
            return $model->active ? 'published' : 'unpublished';
        }

        return 'edited';
    }
}
