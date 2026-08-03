<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveGlobalContentRequest;
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
        $globals = Setting::find('globals')?->value ?? [];

        $globals['notice'] = $request->notice();
        $globals['logo'] = $request->logo();
        $globals['phone'] = $request->phone();
        $globals['nav']['cta']['label'] = $request->ctaLabel();
        $globals['footer'] = array_merge($globals['footer'] ?? [], $request->footer());

        Setting::updateOrCreate(['key' => 'globals'], ['value' => $globals]);

        return back();
    }
}
