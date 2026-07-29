<?php

namespace App\Content;

use App\Models\Page;
use App\Models\PageRevision;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class PageContentStore
{
    public const BLOCK_TYPES = [
        'hero',
        'trust-cards',
        'process-steps',
        'why-list',
        'agent-compare',
        'family',
        'cta',
        'eyebrow',
        'heading',
        'rich-text',
        'image',
        'button',
        'steps-strip',
        'avatar-row',
        'rating-stars',
        'card-grid',
        'step-grid',
        'checklist',
        'benefit-list',
        'trust-marks',
        'stat-stamp',
        'quote-card',
        'info-card',
    ];

    public const CONTAINER_TYPES = [
        'section',
        'row',
        'column',
    ];

    public const SECTION_TYPES = [
        ...self::BLOCK_TYPES,
        'section',
    ];

    public const CHILD_TYPES = [
        'section' => [...self::BLOCK_TYPES, 'row'],
        'row' => ['column'],
        'column' => [...self::BLOCK_TYPES, 'row'],
    ];

    public const MAX_ROW_DEPTH = 2;

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
        return Setting::find('globals')?->value ?? [];
    }

    public function document(string $slug): ?array
    {
        return $this->page($slug)?->toDocument();
    }

    public function findByCmsId(int|string $id): ?string
    {
        if (! ctype_digit((string) $id)) {
            return null;
        }

        return Page::where('cms_id', (int) $id)->value('slug');
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
        $this->page($slug)?->forceFill([
            'draft' => $sections,
            'last_updated_by' => $by,
        ])->save();
    }

    public function publish(string $slug, string $by): void
    {
        $page = $this->page($slug);

        if ($page === null) {
            return;
        }

        $sections = $page->draft ?? $page->published ?? [];

        $page->forceFill([
            'published' => $sections,
            'draft' => null,
            'status' => 'published',
            'published_by' => $by,
            'published_at' => now(),
        ])->save();

        $this->addRevision($page, $sections, $by);
        $this->forget($slug);
    }

    public function revisions(string $slug): array
    {
        $page = $this->page($slug);

        if ($page === null) {
            return [];
        }

        return $page->revisions->map->toSummaryRow()->all();
    }

    public function forget(string $slug): void
    {
        Cache::forget("page:{$slug}:published");
    }

    private function page(string $slug): ?Page
    {
        return Page::where('slug', $slug)->first();
    }

    private function addRevision(Page $page, array $sections, string $by): void
    {
        PageRevision::create([
            'page_id' => $page->id,
            'n' => (int) $page->revisions()->max('n') + 1,
            'action' => 'publish',
            'by' => $by,
            'sections' => $sections,
        ]);

        $page->unsetRelation('revisions');
    }

    private function renderable(array $sections, ?array $allowed = null, int $depth = 0): array
    {
        $allowed ??= self::SECTION_TYPES;
        $visible = [];

        foreach ($sections as $section) {
            if (($section['active'] ?? true) === false) {
                continue;
            }

            $type = $section['type'] ?? '';

            if (! in_array($type, $allowed, true)) {
                continue;
            }

            $next = $type === 'row' ? $depth + 1 : $depth;

            if ($next > self::MAX_ROW_DEPTH) {
                continue;
            }

            if (array_key_exists('children', $section)) {
                $section['children'] = $this->renderable(
                    $section['children'] ?? [],
                    self::CHILD_TYPES[$type] ?? [],
                    $next,
                );
            }

            $visible[] = $section;
        }

        return $visible;
    }

    private function remember(string $key, callable $callback)
    {
        if (config('app.debug')) {
            return $callback();
        }

        return Cache::rememberForever($key, $callback);
    }
}
