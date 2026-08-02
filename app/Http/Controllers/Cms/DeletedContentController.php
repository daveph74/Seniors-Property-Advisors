<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\Faq;
use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Scope §13's "restore recently deleted content where practical".
 *
 * One screen for all three kinds rather than a bin on each list: somebody looking for something
 * they deleted does not always remember what it was filed as, and three empty bins to check is
 * three places to look.
 */
class DeletedContentController extends Controller
{
    public const KINDS = [
        'article' => BlogPost::class,
        'question' => Faq::class,
        'testimonial' => Testimonial::class,
    ];

    public function index(): Response
    {
        $items = collect(self::KINDS)
            ->flatMap(fn (string $model, string $kind) => $model::onlyTrashed()
                ->latest('deleted_at')
                ->get()
                ->map(fn (Model $row) => [
                    'kind' => $kind,
                    'id' => $row->getKey(),
                    'label' => $row->title ?? $row->question ?? $row->name,
                    'deletedAt' => $row->deleted_at?->toIso8601String(),
                ]))
            ->sortByDesc('deletedAt')
            ->values()
            ->all();

        return Inertia::render('Cms/Deleted/Index', ['items' => $items]);
    }

    public function restore(string $kind, int $id): RedirectResponse
    {
        $this->find($kind, $id)->restore();

        return back();
    }

    /**
     * The only way anything leaves for good. Deleting from a list screen is now recoverable, so
     * this is the one place that has to mean it — and it is behind content.delete.
     */
    public function destroy(string $kind, int $id): RedirectResponse
    {
        $this->find($kind, $id)->forceDelete();

        return back();
    }

    private function find(string $kind, int $id): Model
    {
        abort_unless(isset(self::KINDS[$kind]), 404);

        return self::KINDS[$kind]::onlyTrashed()->findOrFail($id);
    }
}
