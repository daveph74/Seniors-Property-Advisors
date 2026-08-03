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
                    'cms_id' => $document['cmsId'],
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
