<?php

namespace App\Http\Controllers\Cms;

use App\Content\PageContentStore;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveNavigationRequest;
use App\Models\Page;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The header and footer menus.
 *
 * These live in the `globals` setting and, until now, only migrations had ever edited them — adding
 * the blog link needed one, and so did the FAQ link. The screen that existed dragged mock rows
 * around local state and saved nothing, which is worse than no screen: it accepted the work and
 * discarded it.
 */
class NavigationController extends Controller
{
    public function __construct(private PageContentStore $store) {}

    public function index(): Response
    {
        $globals = Setting::find('globals')?->value ?? [];

        return Inertia::render('Cms/Navigation/Index', [
            'nav' => array_values($globals['nav']['links'] ?? []),
            'footer' => [
                'columns' => array_values($globals['footer']['columns'] ?? []),
                'links' => array_values($globals['footer']['links'] ?? []),
            ],
            /* Offered as targets, and the reason a label can be read-only: an item pointing at a
               page takes that page's menu label, so editing it here would be overwritten. */
            'pages' => Page::where('status', 'published')
                ->orderBy('cms_id')
                ->get()
                ->map(fn (Page $p) => [
                    'label' => $p->nav_label ?: $p->title,
                    'href' => $p->url,
                    'followsPage' => $p->nav_label !== null,
                ])
                ->all(),
        ]);
    }

    public function update(SaveNavigationRequest $request): RedirectResponse
    {
        $globals = Setting::find('globals')?->value ?? [];

        $globals['nav']['links'] = $request->navLinks();
        $globals['footer']['columns'] = $request->footerColumns();
        $globals['footer']['links'] = $request->footerLinks();

        Setting::updateOrCreate(['key' => 'globals'], ['value' => $globals]);

        /* No cache to clear: the page cache holds section trees, and `globals()` is read fresh on
           every request. A menu change is live immediately. */

        return back();
    }
}
