<?php

namespace App\Http\Controllers\Cms;

use App\Cms\Listing;
use App\Cms\SeoReport;
use App\Content\PageContentStore;
use App\Content\Site;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveSeoDefaultsRequest;
use App\Http\Requests\SaveSeoFieldsRequest;
use App\Models\Activity;
use App\Models\BlogPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * What a search engine finds, and the defaults behind it — two tabs, because they answer each other.
 *
 * The defaults were a tab on `/cms/settings` and moved here rather than being copied: a default
 * description means nothing until you can see the twelve addresses inheriting it, and Settings is
 * `settings.manage` — super administrator only — which would have kept this screen away from the
 * person most likely to want it. Settings keeps that boundary and everything behind it.
 *
 * Only two fields are editable here, and the ones left out are the point. A canonical typed into a
 * list row is a page de-indexed by a fat finger, so it stays in the builder's SEO panel where there
 * is room to say what it does.
 */
class SeoController extends Controller
{
    public function index(Request $request): Response
    {
        $site = Site::all();

        return Inertia::render('Cms/Seo/Index', [
            'defaults' => [
                'titleFormat' => $site['seo']['titleFormat'] ?? '',
                'description' => $site['seo']['description'] ?? '',
                'image' => $site['seo']['image'] ?? '',
            ],
            'filters' => [
                'show' => SeoReport::show($request->query('show')),
                'q' => (string) $request->query('q', ''),
            ],
        ] + $this->report($request));
    }

    public function updateDefaults(SaveSeoDefaultsRequest $request): RedirectResponse
    {
        $before = Site::all()['seo'] ?? [];
        $after = $request->defaults();

        Site::merge(['seo' => $after]);

        /* Settings are not a content model, so nothing observes them — and a default description
           reaching every page in the website should leave the same trail as fixing one typo. */
        if ($before !== $after) {
            Activity::note('edited', 'Settings', 'SEO defaults');
        }

        return back();
    }

    /**
     * One address's own fields. `kind` decides which table, and both merge rather than replace, so
     * this cannot disturb a title or a canonical it never sent.
     */
    public function updateFields(SaveSeoFieldsRequest $request, string $kind, int $id, PageContentStore $store): RedirectResponse
    {
        $fields = $request->fields();

        abort_if($fields === [], 422, 'Nothing to change.');

        if ($kind === 'page') {
            $slug = $store->findByCmsId($id);

            abort_if($slug === null, 404);

            $store->saveDetails($slug, null, $fields, $request->user()->name);

            return back();
        }

        /* Trashed articles are listed on this screen — an address a search engine still holds is
           what somebody comes here to explain — but they are not writable. `findOrFail` excludes
           them, which is the behaviour wanted; it is stated here because the screen also hides the
           editor for them, and a reader could otherwise take this line for an oversight. */
        $post = BlogPost::findOrFail($id);

        $post->forceFill([
            'seo' => array_filter(
                array_merge($post->seo ?? [], $fields),
                fn ($value) => $value !== null && $value !== '',
            ),
            'last_updated_by' => $request->user()->name,
        ])->save();

        return back();
    }

    /**
     * Sliced here rather than by `Listing`, which takes a query builder: these rows come from two
     * tables, and half of what they report — the title after the site format, a description that
     * arrived from the site default, membership of the sitemap — is not a column to sort or filter
     * on. The size allowlist and the `pagination` shape are reused, which is the part that matters.
     *
     * @return array<string, mixed>
     */
    private function report(Request $request): array
    {
        $all = SeoReport::rows();
        $rows = SeoReport::filtered($all, SeoReport::show($request->query('show')), (string) $request->query('q', ''));

        $perPage = Listing::perPage($request);
        $total = count($rows);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $request->integer('page') ?: 1), $lastPage);

        return [
            'rows' => array_slice($rows, ($page - 1) * $perPage, $perPage),
            'counts' => SeoReport::counts($all),
            'truncated' => count($all) > SeoReport::CEILING,
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'lastPage' => $lastPage,
                'from' => $total === 0 ? 0 : ($page - 1) * $perPage + 1,
                'to' => min($total, $page * $perPage),
                'sizes' => Listing::SIZES,
            ],
        ];
    }
}
