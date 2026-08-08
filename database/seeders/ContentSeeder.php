<?php

namespace Database\Seeders;

use App\Content\Site;
use App\Models\Page;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class ContentSeeder extends Seeder
{
    public function run(): void
    {
        foreach (glob(resource_path('content/pages/*.json')) ?: [] as $path) {
            $document = json_decode(file_get_contents($path), true);

            if (! is_array($document) || ! isset($document['slug'])) {
                continue;
            }

            Page::updateOrCreate(
                ['slug' => $document['slug']],
                [
                    'cms_id' => $this->cmsId($document),
                    'url' => $document['url'] ?? '/'.$document['slug'],
                    'title' => $document['title'] ?? '',
                    /* Every seed file has carried a navLabel since they were written and none of
                       them reached the database — the column stayed null on all twelve pages, so
                       the Navigation screen offered every page under its full title. */
                    'nav_label' => $document['navLabel'] ?? null,
                    'status' => $document['status'] ?? 'draft',
                    'seo' => $document['seo'] ?? [],
                    'draft' => $document['draft'] ?? null,
                    'published' => $document['published'] ?? [],
                    'last_updated_by' => $document['last_updated_by'] ?? null,
                    'published_by' => $document['published_by'] ?? null,
                    'published_at' => $document['published_at'] ?? null,
                ],
            );
        }

        $this->settings();
    }

    /**
     * `cms_id` is unique, and a seed file cannot know what a live database has already given
     * out — the id it names may belong to another page entirely, and inserting it would fail.
     * A page that already exists keeps the id it has; otherwise the file's choice is honoured
     * only when it is free, and a fresh one is allocated when it is not.
     */
    private function cmsId(array $document): int
    {
        $existing = Page::where('slug', $document['slug'])->value('cms_id');

        if ($existing !== null) {
            return (int) $existing;
        }

        $wanted = (int) ($document['cmsId'] ?? 0);
        $taken = $wanted < 1 || Page::where('cms_id', $wanted)->exists();

        return $taken ? (int) Page::max('cms_id') + 1 : $wanted;
    }

    /**
     * Written once and never again — `firstOrCreate`, not `updateOrCreate`.
     *
     * These two rows are the only content in the application with no revision history and no
     * draft to fall back on. Between them they hold the menus, the announcement bar, the footer,
     * the SEO defaults and the GA4 and GTM ids, and replacing the row wholesale is unrecoverable:
     * losing the tracking ids in particular fails silently, and nobody notices until a monthly
     * report comes back empty. `GlobalContentController` states the rule this follows — a screen
     * must not delete a value it no longer offers — and a seeder overwriting the whole row was the
     * same mistake at a larger scale.
     *
     * The cost is that a change to these files no longer reaches a database that has them. That
     * is the right way round: the seed file is the starting point for a new installation, and
     * /cms/navigation and /cms/global-content are how a running site is edited.
     */
    private function settings(): void
    {
        foreach (['globals', Site::KEY] as $key) {
            $path = resource_path('content/'.$key.'.json');

            if (is_file($path)) {
                Setting::firstOrCreate(
                    ['key' => $key],
                    ['value' => json_decode(file_get_contents($path), true) ?: []],
                );
            }
        }

        $this->privacyPage();
    }

    /**
     * Points the site at the privacy policy it ships with, on a fresh installation only.
     *
     * The setting names a page by database id, which no seed file can know, so it arrives null —
     * and the consent box on the contact form offers a privacy link only when it resolves. A new
     * site therefore came up asking people to agree to how their details would be handled with
     * nowhere to read it, until somebody thought to set it in Settings.
     *
     * Only fills a blank. A site that has chosen a different page keeps it.
     */
    private function privacyPage(): void
    {
        $site = Setting::find(Site::KEY);
        $value = $site?->value ?? [];

        if ($site === null || ($value['legal']['privacyPage'] ?? null) !== null) {
            return;
        }

        $page = Page::where('slug', 'privacy-policy')->where('status', 'published')->first();

        if ($page === null) {
            return;
        }

        $value['legal']['privacyPage'] = $page->id;

        $site->update(['value' => $value]);
    }
}
