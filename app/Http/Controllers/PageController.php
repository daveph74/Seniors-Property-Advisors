<?php

namespace App\Http\Controllers;

use App\Content\ContentLibrary;
use App\Content\PageContentStore;
use App\Content\Seo;
use App\Content\Site;
use App\Models\Page;
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

        $defaults = Site::seoDefaults();
        $seo = Seo::forSharing($page['seo'] ?? [], $defaults['image'], url()->current());
        $head = Seo::head($seo, $page['title'], 'website', $defaults);
        $globals = $this->store->globals();
        $library = $this->library->for($slug, $this->requestedCategory());

        return Inertia::render('AgentFinder', [
            'title' => $page['title'],
            'seo' => $seo,
            'head' => $head + $this->schema($head, $page, $slug, $globals, $library),
            'sections' => $page['sections'],
            'globals' => $globals,
            'site' => Site::forPublic(),
            'library' => $library,
        ]);
    }

    /**
     * Nothing when the page is hidden from search, the same rule an article follows: structured
     * data describing a page a crawler has been told to forget is at best ignored and at worst
     * read as an attempt to have it both ways.
     */
    private function schema(array $head, array $page, string $slug, array $globals, array $library): array
    {
        if (isset($head['robots'])) {
            return [];
        }

        $defaults = Site::seoDefaults();

        $schema = $slug === 'home'
            ? [Seo::organizationSchema($globals, $defaults, Site::forPublic()['social']), Seo::websiteSchema($defaults)]
            : array_filter([Seo::breadcrumbSchema(url()->current(), $page['title'], $this->ancestorTitles($slug))]);

        $faqs = Seo::faqSchema($page['sections'], $library['faqs'] ?? []);

        if ($faqs !== null) {
            $schema[] = $faqs;
        }

        return $schema === [] ? [] : ['schema' => array_values($schema)];
    }

    /** One query for every address above this page, so a nested page's trail can be named. */
    private function ancestorTitles(string $slug): array
    {
        $segments = array_filter(explode('/', trim($slug, '/')));

        if (count($segments) < 2) {
            return [];
        }

        $walked = '';
        $paths = [];

        foreach ($segments as $segment) {
            $walked .= '/'.$segment;
            $paths[] = $walked;
        }

        return Page::whereIn('url', array_slice($paths, 0, -1))
            ->where('status', 'published')
            ->pluck('title', 'url')
            ->all();
    }
}
