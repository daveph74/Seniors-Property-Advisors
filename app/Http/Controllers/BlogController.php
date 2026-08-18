<?php

namespace App\Http\Controllers;

use App\Content\ContentLibrary;
use App\Content\PageContentStore;
use App\Content\Seo;
use App\Content\Site;
use App\Models\BlogPost;
use App\Models\PageRedirect;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BlogController extends Controller
{
    public function __construct(
        private readonly PageContentStore $store,
        private readonly ContentLibrary $library,
    ) {}

    public function show(string $article): Response|RedirectResponse
    {
        $post = BlogPost::published()->with('categories')->where('slug', $article)->first();

        if ($post === null) {
            $moved = PageRedirect::where('from_url', "/blog/{$article}")->value('to_url');

            if ($moved !== null) {
                return redirect()->to($moved, 301);
            }

            abort(404);
        }

        $defaults = Site::seoDefaults();
        $seo = $this->sharing($post, $defaults['image']);
        $article = $post->toArticle();
        $head = Seo::head($seo, $post->title, 'article', $defaults);

        return Inertia::render('Article', [
            'article' => $article,
            'seo' => $seo,
            /* No structured data on anything hidden from search: marking up a page as an article
               while asking not to be listed sends a crawler two different instructions. */
            'head' => $head + (isset($head['robots'])
                ? []
                : ['schema' => Seo::articleSchema($head, $article, $defaults['name'])]),
            'related' => $this->library->related($post),
            'globals' => $this->store->globals(),
            'site' => Site::forPublic(),
        ]);
    }

    /**
     * The featured image stands in when an article has no sharing image of its own, and the
     * fallback is resolved here rather than in the page component so the sharing tags have one
     * source. `Cms\BlogController::preview()` builds the same shape.
     */
    public static function sharing(BlogPost $post, ?string $fallbackImage = null): array
    {
        return Seo::forSharing(
            array_merge(
                ['title' => $post->title, 'description' => $post->summary],
                array_filter($post->seo ?? [], fn ($value) => $value !== null && $value !== ''),
            ),
            /* The article's own picture first, then the site default — the site image is a
               last resort, not a replacement for a picture chosen for this piece. */
            $post->featured_image ?: $fallbackImage,
            url($post->url()),
        );
    }

    /**
     * The next page of cards for the listing's load-more control. Kept separate from the
     * page payload so a site with many articles does not ship them all on every render, and
     * it carries the category so paging inside a filtered view stays inside it.
     */
    public function articles(): JsonResponse
    {
        $page = max(1, (int) request()->integer('page', 1));
        $category = trim((string) request()->query('category'));

        return response()->json($this->library->posts(
            $page,
            preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $category) === 1 ? $category : null,
        ));
    }
}
