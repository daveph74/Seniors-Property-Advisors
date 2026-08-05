<?php

namespace App\Http\Controllers\Cms;

use App\Content\ContentLibrary;
use App\Content\PageContentStore;
use App\Content\ReusableSectionStore;
use App\Content\SectionDiff;
use App\Content\Seo;
use App\Content\StarterLayouts;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreatePageRequest;
use App\Http\Requests\SavePageDetailsRequest;
use App\Http\Requests\SaveSectionsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CmsPageController extends Controller
{
    public function __construct(
        private readonly PageContentStore $store,
        private readonly ContentLibrary $library,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Cms/Pages/Index', [
            'pages' => $this->store->all(),
            'layouts' => StarterLayouts::options(),
        ]);
    }

    public function store(CreatePageRequest $request): RedirectResponse
    {
        $page = $this->store->create(
            $request->title(),
            $request->parent(),
            StarterLayouts::sections($request->layout()),
            $this->author(),
        );

        return redirect()->route('cms.pages.edit', $page['id']);
    }

    public function edit(string $page): Response
    {
        $slug = $this->store->findByCmsId($page);

        /* The save route already 404s an unknown id, so rendering the builder for one only ever
           offered a page that could never be saved — and the prototype's fallback titled it from
           an invented list, so an id that happened to match showed a page that does not exist. */
        abort_if($slug === null, 404);

        $document = $this->store->document($slug);

        return Inertia::render('Cms/Pages/Builder', [
            'pageId' => $page,
            'sections' => $this->store->editable($slug),
            'page' => [
                'slug' => ltrim($document['url'] ?? $slug, '/'),
                'title' => $document['title'] ?? '',
                'navLabel' => $document['navLabel'] ?? null,
                'url' => $document['url'] ?? '/',
                'seo' => $document['seo'] ?? [],
                'status' => $document['status'] ?? 'draft',
                'hasDraft' => ($document['draft'] ?? null) !== null,
                'lastUpdatedBy' => $document['last_updated_by'] ?? null,
                'publishedAt' => $document['published_at'] ?? null,
            ],
            'revisions' => $this->store->revisions($slug),
            'globals' => $this->store->globals(),
            'library' => $this->library->for($slug) + $this->library->choices(),
            'reusables' => (new ReusableSectionStore)->all(),
        ]);
    }

    public function saveDraft(SaveSectionsRequest $request, string $page): RedirectResponse
    {
        $slug = $this->resolveSlug($page);

        $this->store->saveDraft($slug, $request->sections(), $this->author());

        return back();
    }

    public function saveDetails(SavePageDetailsRequest $request, string $page): RedirectResponse
    {
        $slug = $this->resolveSlug($page);

        $this->store->saveDetails(
            $slug,
            $request->title(),
            $request->seo(),
            $this->author(),
            $request->navLabel(),
            $request->slug(),
        );

        return back();
    }

    public function publish(SaveSectionsRequest $request, string $page): RedirectResponse
    {
        $slug = $this->resolveSlug($page);

        $this->store->saveDraft($slug, $request->sections(), $this->author());
        $this->store->publish($slug, $this->author());

        return back();
    }

    public function publishNow(string $page): RedirectResponse
    {
        $slug = $this->resolveSlug($page);

        $this->store->setStatus($slug, 'published', $this->author());
        $this->store->publish($slug, $this->author());

        return back();
    }

    public function unpublish(string $page): RedirectResponse
    {
        abort_if(! $this->store->setStatus($this->resolveSlug($page), 'draft', $this->author()), 404);

        return back();
    }

    public function archive(string $page): RedirectResponse
    {
        abort_if(! $this->store->setStatus($this->resolveSlug($page), 'archived', $this->author()), 404);

        return back();
    }

    public function unarchive(string $page): RedirectResponse
    {
        $slug = $this->resolveSlug($page);
        $document = $this->store->document($slug);
        $status = ($document['published'] ?? []) === [] ? 'draft' : 'published';

        abort_if(! $this->store->setStatus($slug, $status, $this->author()), 404);

        return back();
    }

    public function duplicate(string $page): RedirectResponse
    {
        $copy = $this->store->duplicate($this->resolveSlug($page), $this->author());

        abort_if($copy === null, 404);

        return back();
    }

    public function preview(string $page): Response
    {
        return $this->renderPreview($page, $this->store->preview($this->resolveSlug($page)));
    }

    public function previewRevision(string $page, int $n): Response
    {
        return $this->renderPreview($page, $this->store->previewRevision($this->resolveSlug($page), $n));
    }

    public function changes(SaveSectionsRequest $request, string $page): JsonResponse
    {
        $document = $this->store->document($this->resolveSlug($page));

        return response()->json(
            SectionDiff::between($document['published'] ?? [], $request->sections()),
        );
    }

    public function compare(string $page, int $n): JsonResponse
    {
        $slug = $this->resolveSlug($page);
        $sections = $this->store->revisionSections($slug, $n);

        abort_if($sections === null, 404);

        $against = request()->integer('against');
        $document = $this->store->document($slug);

        $other = $against > 0
            ? $this->store->revisionSections($slug, $against)
            : ($document['published'] ?? []);

        abort_if($other === null, 404);

        return response()->json([
            'n' => $n,
            'against' => $against > 0 ? $against : 'live',
        ] + SectionDiff::between($sections, $other));
    }

    public function restore(string $page, int $n): RedirectResponse
    {
        $slug = $this->resolveSlug($page);

        abort_if(! $this->store->restore($slug, $n, $this->author()), 404);

        return back();
    }

    public function revisions(string $page): JsonResponse
    {
        $before = request()->integer('before');

        return response()->json($this->store->revisions(
            $this->resolveSlug($page),
            before: $before > 0 ? $before : null,
        ));
    }

    private function renderPreview(string $page, ?array $data): Response
    {
        abort_if($data === null, 404);

        return Inertia::render('AgentFinder', [
            'title' => $data['title'],
            'seo' => Seo::forSharing($data['seo'] ?? [], null, url()->current()),
            'sections' => $data['sections'],
            'globals' => $this->store->globals(),
            'library' => $this->library->for($this->resolveSlug($page)),
            'preview' => [
                'mode' => $data['source'],
                'n' => $data['n'] ?? null,
                'by' => $data['by'] ?? null,
                'at' => $data['at'] ?? null,
                'editUrl' => route('cms.pages.edit', $page),
            ],
        ]);
    }

    private function resolveSlug(string $page): string
    {
        $slug = $this->store->findByCmsId($page);

        abort_if($slug === null, 404);

        return $slug;
    }

    private function author(): string
    {
        return (string) request()->user()->name;
    }
}
