<?php

namespace App\Console\Commands;

use App\Content\PageContentStore;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\Setting;
use Illuminate\Console\Command;

class ImportContentFiles extends Command
{
    protected $signature = 'content:import {--force : Overwrite pages that already exist}';

    protected $description = 'Import page content from storage/app/content into the database';

    public function handle(PageContentStore $store): int
    {
        $root = storage_path('app/content');

        if (! is_dir($root)) {
            $this->warn("Nothing to import: {$root} does not exist.");

            return self::SUCCESS;
        }

        $imported = 0;
        $skipped = 0;

        foreach (glob("{$root}/pages/*.json") ?: [] as $path) {
            $document = json_decode(file_get_contents($path), true);

            if (! is_array($document) || ! isset($document['slug'])) {
                $this->error('Skipped unreadable file: '.basename($path));

                continue;
            }

            $slug = $document['slug'];
            $existing = Page::where('slug', $slug)->first();

            if ($existing && ! $this->option('force')) {
                $this->line("Skipped {$slug} (already exists, use --force to overwrite).");
                $skipped++;

                continue;
            }

            $page = Page::updateOrCreate(['slug' => $slug], [
                'cms_id' => $document['cmsId'],
                'url' => $document['url'] ?? '/'.$slug,
                'title' => $document['title'] ?? '',
                'status' => $document['status'] ?? 'draft',
                'seo' => $document['seo'] ?? [],
                'draft' => $document['draft'] ?? null,
                'published' => $document['published'] ?? [],
                'last_updated_by' => $document['last_updated_by'] ?? null,
                'published_by' => $document['published_by'] ?? null,
                'published_at' => $document['published_at'] ?? null,
            ]);

            $this->importRevisions($page, "{$root}/revisions/{$slug}.json");
            $store->forget($slug);

            $this->info("Imported {$slug}.");
            $imported++;
        }

        $globals = "{$root}/globals.json";

        if (is_file($globals)) {
            Setting::updateOrCreate(
                ['key' => 'globals'],
                ['value' => json_decode(file_get_contents($globals), true) ?: []],
            );

            $this->info('Imported globals.');
        }

        $this->line("Done. {$imported} imported, {$skipped} skipped.");

        return self::SUCCESS;
    }

    private function importRevisions(Page $page, string $path): void
    {
        if (! is_file($path)) {
            return;
        }

        foreach (json_decode(file_get_contents($path), true) ?: [] as $revision) {
            if (! isset($revision['n'])) {
                continue;
            }

            $row = PageRevision::updateOrCreate(
                ['page_id' => $page->id, 'n' => $revision['n']],
                [
                    'action' => $revision['action'] ?? 'publish',
                    'by' => $revision['by'] ?? '',
                    'sections' => $revision['sections'] ?? [],
                ],
            );

            if (! empty($revision['at'])) {
                $row->forceFill(['created_at' => $revision['at']])->saveQuietly();
            }
        }
    }
}
