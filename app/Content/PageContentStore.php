<?php

namespace App\Content;

use Illuminate\Support\Facades\Cache;

class PageContentStore
{
    public const SECTION_TYPES = [
        'hero',
        'trust-cards',
        'process-steps',
        'why-list',
        'agent-compare',
        'family',
        'cta',
    ];

    public function resolve(string $slug): ?array
    {
        return $this->remember("page:{$slug}:published", function () use ($slug) {
            $page = $this->document($slug);

            if ($page === null || ($page['status'] ?? null) !== 'published') {
                return null;
            }

            return [
                'title' => $page['title'] ?? '',
                'seo' => $page['seo'] ?? [],
                'sections' => $this->renderable($page['published'] ?? []),
            ];
        });
    }

    public function globals(): array
    {
        return $this->remember('content:globals', fn () => $this->read('globals') ?? []);
    }

    public function document(string $slug): ?array
    {
        return $this->read("pages/{$slug}");
    }

    public function findByCmsId(int|string $id): ?string
    {
        foreach ($this->slugs() as $slug) {
            $document = $this->document($slug);

            if ($document !== null && (string) ($document['cmsId'] ?? '') === (string) $id) {
                return $slug;
            }
        }

        return null;
    }

    public function editable(string $slug): ?array
    {
        $document = $this->document($slug);

        if ($document === null) {
            return null;
        }

        return $document['draft'] ?? $document['published'] ?? [];
    }

    public function saveDraft(string $slug, array $sections, string $by): void
    {
        $document = $this->document($slug);

        if ($document === null) {
            return;
        }

        $document['draft'] = $sections;
        $document['last_updated_by'] = $by;
        $document['updated_at'] = now()->toIso8601String();

        $this->write($slug, $document);
    }

    public function publish(string $slug, string $by): void
    {
        $document = $this->document($slug);

        if ($document === null) {
            return;
        }

        $sections = $document['draft'] ?? $document['published'] ?? [];

        $document['published'] = $sections;
        $document['draft'] = null;
        $document['status'] = 'published';
        $document['published_by'] = $by;
        $document['published_at'] = now()->toIso8601String();

        $this->write($slug, $document);
        $this->addRevision($slug, $sections, $by);
        $this->forget($slug);
    }

    public function revisions(string $slug): array
    {
        $path = storage_path("app/content/revisions/{$slug}.json");

        if (! is_file($path)) {
            return [];
        }

        return json_decode(file_get_contents($path), true) ?: [];
    }

    public function forget(string $slug): void
    {
        Cache::forget("page:{$slug}:published");
    }

    private function addRevision(string $slug, array $sections, string $by): void
    {
        $revisions = $this->revisions($slug);

        array_unshift($revisions, [
            'n' => count($revisions) + 1,
            'action' => 'publish',
            'by' => $by,
            'at' => now()->toIso8601String(),
            'sections' => $sections,
        ]);

        $this->putJson(storage_path("app/content/revisions/{$slug}.json"), $revisions);
    }

    private function renderable(array $sections): array
    {
        $visible = array_filter($sections, function ($section) {
            return ($section['active'] ?? true) !== false
                && in_array($section['type'] ?? '', self::SECTION_TYPES, true);
        });

        return array_values($visible);
    }

    private function slugs(): array
    {
        $slugs = [];

        foreach ([storage_path('app/content/pages'), resource_path('content/pages')] as $directory) {
            foreach (glob("{$directory}/*.json") ?: [] as $path) {
                $slugs[basename($path, '.json')] = true;
            }
        }

        return array_keys($slugs);
    }

    private function read(string $name): ?array
    {
        if (! preg_match('#^[a-z0-9\-/]+$#', $name) || str_contains($name, '..')) {
            return null;
        }

        $candidates = [
            storage_path("app/content/{$name}.json"),
            resource_path("content/{$name}.json"),
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return json_decode(file_get_contents($path), true);
            }
        }

        return null;
    }

    private function write(string $slug, array $document): void
    {
        if (! preg_match('#^[a-z0-9\-]+$#', $slug)) {
            return;
        }

        $this->putJson(storage_path("app/content/pages/{$slug}.json"), $document);
    }

    private function putJson(string $path, array $payload): void
    {
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

        file_put_contents($path, json_encode($payload, $flags).PHP_EOL);
    }

    private function remember(string $key, callable $callback)
    {
        if (config('app.debug')) {
            return $callback();
        }

        return Cache::rememberForever($key, $callback);
    }
}
