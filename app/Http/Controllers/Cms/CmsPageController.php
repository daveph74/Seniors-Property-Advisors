<?php

namespace App\Http\Controllers\Cms;

use App\Content\PageContentStore;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveSectionsRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CmsPageController extends Controller
{
    public function __construct(private readonly PageContentStore $store) {}

    public function edit(string $page): Response
    {
        $slug = $this->store->findByCmsId($page);

        if ($slug === null) {
            return Inertia::render('Cms/Pages/Builder', ['pageId' => $page]);
        }

        $document = $this->store->document($slug);

        return Inertia::render('Cms/Pages/Builder', [
            'pageId' => $page,
            'sections' => $this->store->editable($slug),
            'page' => [
                'slug' => ltrim($document['url'] ?? $slug, '/'),
                'title' => $document['title'] ?? '',
                'status' => $document['status'] ?? 'draft',
                'hasDraft' => ($document['draft'] ?? null) !== null,
                'lastUpdatedBy' => $document['last_updated_by'] ?? null,
                'publishedAt' => $document['published_at'] ?? null,
            ],
            'revisions' => $this->revisionSummaries($slug),
            'globals' => $this->store->globals(),
        ]);
    }

    public function saveDraft(SaveSectionsRequest $request, string $page): RedirectResponse
    {
        $slug = $this->resolveSlug($page);

        $this->store->saveDraft($slug, $request->sections(), $this->author());

        return back();
    }

    public function publish(SaveSectionsRequest $request, string $page): RedirectResponse
    {
        $slug = $this->resolveSlug($page);

        $this->store->saveDraft($slug, $request->sections(), $this->author());
        $this->store->publish($slug, $this->author());

        return back();
    }

    private function resolveSlug(string $page): string
    {
        $slug = $this->store->findByCmsId($page);

        abort_if($slug === null, 404);

        return $slug;
    }

    private function author(): string
    {
        return request()->user()?->name ?? 'Helen Marsh';
    }

    private function revisionSummaries(string $slug): array
    {
        return array_map(fn ($revision) => [
            'n' => $revision['n'],
            'action' => $revision['action'],
            'by' => $revision['by'],
            'at' => $revision['at'],
            'sections' => count($revision['sections'] ?? []),
        ], $this->store->revisions($slug));
    }
}
