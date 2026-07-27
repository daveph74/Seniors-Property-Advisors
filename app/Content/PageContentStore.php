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
            $page = $this->read("pages/{$slug}");

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

    public function forget(string $slug): void
    {
        Cache::forget("page:{$slug}:published");
    }

    private function renderable(array $sections): array
    {
        $visible = array_filter($sections, function ($section) {
            return ($section['active'] ?? true) !== false
                && in_array($section['type'] ?? '', self::SECTION_TYPES, true);
        });

        return array_values($visible);
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

    private function remember(string $key, callable $callback)
    {
        if (config('app.debug')) {
            return $callback();
        }

        return Cache::rememberForever($key, $callback);
    }
}
