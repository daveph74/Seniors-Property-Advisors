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

    private function settings(): void
    {
        foreach (['globals', Site::KEY] as $key) {
            $path = resource_path('content/'.$key.'.json');

            if (is_file($path)) {
                Setting::updateOrCreate(
                    ['key' => $key],
                    ['value' => json_decode(file_get_contents($path), true) ?: []],
                );
            }
        }
    }
}
