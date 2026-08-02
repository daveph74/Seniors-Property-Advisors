<?php

namespace App\Http\Controllers;

use App\Content\ContentLibrary;
use App\Content\PageContentStore;
use App\Content\Seo;
use App\Models\PageRedirect;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function __construct(
        private readonly PageContentStore $store,
        private readonly ContentLibrary $library,
    ) {}

    public function home(): Response
    {
        return $this->render($this->store->resolve('home'), 'home');
    }

    public function show(string $path): Response|RedirectResponse
    {
        $page = $this->store->resolve($path);

        if ($page === null) {
            $moved = PageRedirect::where('from_url', '/'.trim($path, '/'))->value('to_url');

            if ($moved !== null) {
                return redirect()->to($moved, 301);
            }
        }

        return $this->render($page, $path);
    }

    /**
     * A listing page carrying a blog-list section reads ?category=… so a filtered view is a
     * real address. Any other page simply ignores it.
     */
    private function requestedCategory(): ?string
    {
        $category = trim((string) request()->query('category'));

        return preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $category) === 1 ? $category : null;
    }

    private function render(?array $page, string $slug): Response
    {
        abort_if($page === null, 404);

        $seo = Seo::forSharing($page['seo'] ?? [], null, url()->current());

        return Inertia::render('AgentFinder', [
            'title' => $page['title'],
            'seo' => $seo,
            'head' => Seo::head($seo, $page['title']),
            'sections' => $page['sections'],
            'globals' => $this->store->globals(),
            'library' => $this->library->for($slug, $this->requestedCategory()),
        ]);
    }
}
