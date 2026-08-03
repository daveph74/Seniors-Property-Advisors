<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveGlobalContentRequest;
use App\Models\Activity;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The wording that sits outside any one page: the announcement bar, the brand, the phone number,
 * the header button and the footer's own text.
 *
 * These share the `globals` setting with the menus, which `NavigationController` owns. The split is
 * by what a person is doing rather than by where it is stored — nobody sits down to "edit globals",
 * they either rearrange a menu or reword the footer. Both controllers merge into the existing row
 * rather than replacing it, so neither can wipe the other's half.
 */
class GlobalContentController extends Controller
{
    public function index(): Response
    {
        $globals = Setting::find('globals')?->value ?? [];

        return Inertia::render('Cms/Global/Index', [
            'globals' => [
                'notice' => [
                    /* Absent means showing: the bar predates this screen and was always visible. */
                    'active' => ($globals['notice']['active'] ?? true) !== false,
                    'text' => $globals['notice']['text'] ?? '',
                    'href' => $globals['notice']['href'] ?? '',
                ],
                'logo' => [
                    'src' => $globals['logo']['src'] ?? '',
                    'alt' => $globals['logo']['alt'] ?? '',
                ],
                'phone' => [
                    'label' => $globals['phone']['label'] ?? '',
                    'href' => $globals['phone']['href'] ?? '',
                ],
                'cta' => ['label' => $globals['nav']['cta']['label'] ?? ''],
                'footer' => [
                    'word' => $globals['footer']['word'] ?? '',
                    'blurb' => $globals['footer']['blurb'] ?? '',
                    'address' => implode("\n", $globals['footer']['address'] ?? []),
                    'legal' => $globals['footer']['legal'] ?? '',
                ],
            ],
        ]);
    }

    public function update(SaveGlobalContentRequest $request): RedirectResponse
    {
        $before = Setting::find('globals')?->value ?? [];
        $globals = $before;

        $globals['notice'] = $request->notice();
        $globals['logo'] = $request->logo();
        $globals['phone'] = $request->phone();
        $globals['nav']['cta']['label'] = $request->ctaLabel();
        $globals['footer'] = array_merge($globals['footer'] ?? [], $request->footer());

        Setting::updateOrCreate(['key' => 'globals'], ['value' => $globals]);

        $this->record($before, $globals);

        return back();
    }

    /**
     * §13 watches content models, and this is not one — nothing here is a row, so an observer has
     * nothing to hang off. Without this a person could reword every page in the website and the log
     * would show no trace of it, while a typo fixed in one article shows up twice.
     *
     * Named by what moved rather than logged as one flat "edited": the whole screen saves at once,
     * so "edited the global content" alone would never say which part.
     */
    private function record(array $before, array $after): void
    {
        $areas = array_keys(array_filter([
            'Announcement bar' => ($before['notice'] ?? null) !== $after['notice'],
            'Logo' => ($before['logo'] ?? null) !== $after['logo'],
            'Phone number' => ($before['phone'] ?? null) !== $after['phone'],
            'Header button' => ($before['nav']['cta']['label'] ?? null) !== $after['nav']['cta']['label'],
            'Footer' => ($before['footer'] ?? null) !== $after['footer'],
        ]));

        /* Saving a screen without changing anything is not an event. */
        if ($areas !== []) {
            Activity::note('edited', 'GlobalContent', implode(', ', $areas));
        }
    }
}
