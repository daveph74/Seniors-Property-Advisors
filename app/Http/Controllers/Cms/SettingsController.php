<?php

namespace App\Http\Controllers\Cms;

use App\Content\Site;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveSettingsRequest;
use App\Models\Activity;
use App\Models\Page;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Site settings — the defaults and switches set once, behind `settings.manage`.
 *
 * The screen this replaces was a four-tab form with every value hardcoded and a Save button that
 * only raised a toast. Its General tab also invented a business: an ABN, a Hawthorn address, a
 * domain and two social profiles, none of which match the real content. Plausible fiction in an
 * admin screen is worse than an empty one, so those went rather than being wired up.
 *
 * What is not here is deliberate. The phone number, office address, email and copyright line
 * already live in Global content; a second copy is a copy that disagrees. The registered name and
 * ABN are inside the footer's copyright line for the same reason.
 */
class SettingsController extends Controller
{
    public function index(): Response
    {
        $site = Site::all();

        return Inertia::render('Cms/Settings/Index', [
            'settings' => [
                'name' => $site['name'] ?? '',
                'favicon' => $site['favicon'] ?? '',
                'seo' => [
                    'titleFormat' => $site['seo']['titleFormat'] ?? '',
                    'description' => $site['seo']['description'] ?? '',
                    'image' => $site['seo']['image'] ?? '',
                ],
                'social' => [
                    'facebook' => $site['social']['facebook'] ?? '',
                    'linkedin' => $site['social']['linkedin'] ?? '',
                ],
                'tracking' => [
                    'ga4' => $site['tracking']['ga4'] ?? '',
                    'gtm' => $site['tracking']['gtm'] ?? '',
                ],
                'legal' => [
                    'disclaimer' => $site['legal']['disclaimer'] ?? '',
                    'privacyPage' => $site['legal']['privacyPage'] ?? '',
                ],
            ],
            /* Published only, matching the rule. Offering a draft would put a consent line in
               front of a 404 — see `Site::pageUrl()`. */
            'pages' => Page::where('status', 'published')
                ->orderBy('cms_id')
                ->get(['id', 'title', 'url'])
                ->map(fn (Page $p) => ['id' => $p->id, 'label' => $p->title, 'url' => $p->url])
                ->all(),
        ]);
    }

    public function update(SaveSettingsRequest $request): RedirectResponse
    {
        $before = Site::all();
        $after = $request->settings();

        Setting::updateOrCreate(['key' => Site::KEY], ['value' => $after]);

        $this->record($before, $after);

        return back();
    }

    /**
     * §13 again: settings are not a content model, so nothing observes them. A default description
     * or an analytics tag reaching every page in the website should leave the same trail as fixing
     * a typo in one article.
     */
    private function record(array $before, array $after): void
    {
        $areas = array_keys(array_filter([
            'Website name' => ($before['name'] ?? null) !== $after['name'],
            'Favicon' => ($before['favicon'] ?? null) !== $after['favicon'],
            'SEO defaults' => ($before['seo'] ?? null) !== $after['seo'],
            'Social links' => ($before['social'] ?? null) !== $after['social'],
            'Tracking' => ($before['tracking'] ?? null) !== $after['tracking'],
            'Legal' => ($before['legal'] ?? null) !== $after['legal'],
        ]));

        if ($areas !== []) {
            Activity::note('edited', 'Settings', implode(', ', $areas));
        }
    }
}
