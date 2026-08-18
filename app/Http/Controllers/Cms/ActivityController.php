<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityController extends Controller
{
    public const PER_PAGE = 60;

    public function index(Request $request): Response
    {
        $action = (string) $request->query('action', '');
        $type = (string) $request->query('type', '');

        $entries = Activity::query()
            ->when($action !== '', fn ($q) => $q->where('action', $action))
            ->when($type !== '', fn ($q) => $q->where('subject_type', $type))
            ->latest('id')
            ->limit(self::PER_PAGE)
            ->get();

        return Inertia::render('Cms/Activity/Index', [
            'entries' => $entries->map(fn (Activity $a) => [
                'id' => $a->id,
                'action' => $a->action,
                'type' => $a->subject_type,
                'label' => $a->subjectLabel(),
                /* No name means nobody was signed in — a console command or a seed, not a person
                   whose account has gone. The name is copied at the time for that case. */
                'by' => $a->by_name ?? 'System',
                'at' => $a->created_at?->toIso8601String(),
            ])->all(),
            'filters' => ['action' => $action, 'type' => $type],
            /* Only what has actually happened — an empty filter to choose is a dead end. */
            'actions' => Activity::query()->distinct()->orderBy('action')->pluck('action')->all(),
            'types' => Activity::query()->distinct()->orderBy('subject_type')->pluck('subject_type')->all(),
            'perPage' => self::PER_PAGE,
        ]);
    }
}
