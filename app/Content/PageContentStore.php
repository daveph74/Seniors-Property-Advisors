<?php

namespace App\Content;

use App\Models\Page;
use App\Models\PageRedirect;
use App\Models\PageRevision;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
        'text-image',
        'stat-row',
        'testimonials',
        'faq-list',
        'team-intro',
        'contact-form',
        'blog-list',
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

    public const RESERVED_SLUGS = [
        'cms', 'build', 'storage', 'up', 'api', 'login', 'logout', 'register', 'home',
    ];

    /**
     * Namespaces owned by a dedicated route. A page at "blog" is the article listing and
     * is allowed; anything beneath it would be shadowed by /blog/{article} and could never
     * be viewed, so it is refused rather than created and left broken.
     */
    public const RESERVED_PREFIXES = ['blog'];

    public static function slugIsReserved(string $slug): bool
    {
        $slug = trim($slug, '/');

        if (in_array($slug, self::RESERVED_SLUGS, true)) {
            return true;
        }

        foreach (self::RESERVED_PREFIXES as $prefix) {
            if (str_starts_with($slug, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }

    public function resolve(string $slug): ?array
    {
        return $this->remember($this->cacheKey($slug), function () use ($slug) {
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

    public function preview(string $slug): ?array
    {
        $document = $this->document($slug);

        if ($document === null) {
            return null;
        }

        $draft = $document['draft'] ?? null;

        return [
            'title' => $document['title'] ?? '',
            'seo' => $document['seo'] ?? [],
            'sections' => $this->legal($draft ?? $document['published'] ?? [], null, 0, true),
            'source' => $draft !== null ? 'draft' : 'published',
        ];
    }

    public function previewRevision(string $slug, int $n): ?array
    {
        $page = $this->page($slug);
        $revision = $page?->revisions()->where('n', $n)->first();

        if ($revision === null) {
            return null;
        }

        return [
            'title' => $page->title ?? '',
            'seo' => $page->seo ?? [],
            'sections' => $this->legal($revision->sections ?? [], null, 0, true),
            'source' => 'revision',
            'n' => $revision->n,
            'at' => $revision->created_at?->toIso8601String(),
            'by' => $revision->by,
        ];
    }

    public function globals(): array
    {
        $globals = Setting::find('globals')?->value ?? [];
        $labels = Page::whereNotNull('nav_label')->pluck('nav_label', 'url');

        if ($labels->isEmpty() || ! isset($globals['nav']['links'])) {
            return $globals;
        }

        $globals['nav']['links'] = $this->labelled($globals['nav']['links'], $labels);

        return $globals;
    }

    /**
     * A menu item pointing at a page takes that page's menu label, so renaming it in the CMS
     * renames the menu. Children are walked too — Blog sits under Resources, and a nested item
     * that stopped following its page would be a quiet inconsistency.
     */
    private function labelled(array $links, $labels): array
    {
        return array_map(function (array $link) use ($labels) {
            if (isset($link['children']) && is_array($link['children'])) {
                $link['children'] = $this->labelled($link['children'], $labels);
            }

            $label = $labels[$link['href'] ?? ''] ?? null;

            return $label === null ? $link : ['label' => $label] + $link;
        }, $links);
    }

    public function all(): array
    {
        return Page::orderBy('cms_id')->get()->map(fn (Page $page) => [
            'id' => $page->cms_id,
            'title' => $page->title,
            'navLabel' => $page->nav_label,
            'url' => $page->url,
            'status' => $page->draft !== null && $page->status === 'published' ? 'changes' : $page->status,
            'sectionCount' => count($page->draft ?? $page->published ?? []),
            'updatedAt' => $page->updated_at?->toIso8601String(),
            'by' => $page->last_updated_by ?? $page->published_by,
            'depth' => max(0, substr_count(rtrim($page->url, '/'), '/') - 1),
        ])->all();
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

    public function saveDetails(
        string $slug,
        string $title,
        array $seo,
        string $by,
        ?string $navLabel = null,
        ?string $newSlug = null,
    ): bool {
        $page = $this->page($slug);

        if ($page === null) {
            return false;
        }

        $changes = [
            'title' => $title,
            /* Null and empty are dropped, but `false` is kept: switching "hide from search engines"
               off writes false, and discarding it would merge the old true straight back in. */
            'seo' => array_filter(
                array_merge($page->seo ?? [], $seo),
                fn ($value) => $value !== null && $value !== '',
            ),
            'last_updated_by' => $by,
        ];

        if ($navLabel !== null) {
            $changes['nav_label'] = $navLabel === '' ? null : $navLabel;
        }

        $moved = $this->renameTo($page, $newSlug);

        if ($moved !== null) {
            $changes['slug'] = $moved;
            $changes['url'] = '/'.$moved;
        }

        $page->forceFill($changes)->save();

        $this->forget($slug);

        if ($moved !== null) {
            $this->forget($moved);
        }

        return true;
    }

    private function renameTo(Page $page, ?string $requested): ?string
    {
        $target = trim((string) $requested, '/');

        if ($target === '' || $target === $page->slug || $page->slug === 'home' || self::slugIsReserved($target)) {
            return null;
        }

        $target = $this->availableSlug($target);
        $from = $page->url;
        $to = '/'.$target;

        if ($page->status === 'published') {
            PageRedirect::where('to_url', $from)->update(['to_url' => $to]);

            PageRedirect::updateOrCreate(
                ['from_url' => $from],
                ['to_url' => $to, 'page_id' => $page->id],
            );
        }

        PageRedirect::where('from_url', $to)->delete();

        return $target;
    }

    public function saveDraft(string $slug, array $sections, string $by): void
    {
        $this->page($slug)?->forceFill([
            'draft' => $sections,
            'last_updated_by' => $by,
        ])->save();
    }

    public function publish(string $slug, string $by): bool
    {
        $page = $this->page($slug);

        if ($page === null) {
            return false;
        }

        $sections = $page->draft ?? $page->published ?? [];
        $changed = $sections !== ($page->published ?? []) || $page->revisions()->count() === 0;

        $page->forceFill([
            'published' => $sections,
            'draft' => null,
            'status' => 'published',
            'published_by' => $by,
            'published_at' => now(),
        ])->save();

        if ($changed) {
            $this->addRevision($page, $sections, $by);
        }

        $this->forget($slug);

        return $changed;
    }

    public function setStatus(string $slug, string $status, string $by): bool
    {
        $page = $this->page($slug);

        if ($page === null || ! in_array($status, ['draft', 'published', 'archived'], true)) {
            return false;
        }

        $page->forceFill(['status' => $status, 'last_updated_by' => $by])->save();
        $this->forget($slug);

        return true;
    }

    public function create(string $title, ?string $parent, array $sections, string $by): array
    {
        $prefix = ($parent !== null && $parent !== '' && $parent !== 'home')
            ? rtrim($parent, '/').'/'
            : '';

        $page = $this->insert(
            $this->availableSlug($prefix.Str::slug($title)),
            $title,
            ['seo' => [], 'draft' => $sections],
            $by,
        );

        return ['id' => $page->cms_id, 'slug' => $page->slug, 'url' => $page->url, 'title' => $page->title];
    }

    public function duplicate(string $slug, string $by): ?array
    {
        $source = $this->page($slug);

        if ($source === null) {
            return null;
        }

        $copy = $this->insert(
            $this->availableSlug($source->slug.'-copy'),
            "{$source->title} (copy)",
            ['seo' => $source->seo ?? [], 'draft' => $source->draft ?? $source->published ?? []],
            $by,
        );

        return ['id' => $copy->cms_id, 'title' => $copy->title];
    }

    public function restore(string $slug, int $n, string $by): bool
    {
        $page = $this->page($slug);
        $revision = $page?->revisions()->where('n', $n)->first();

        if ($revision === null) {
            return false;
        }

        $page->forceFill([
            'draft' => $this->legal($revision->sections ?? []),
            'last_updated_by' => $by,
        ])->save();

        return true;
    }

    public function revisionSections(string $slug, int $n): ?array
    {
        return $this->page($slug)?->revisions()->where('n', $n)->first()?->sections;
    }

    public function revisions(string $slug, int $limit = 50, ?int $before = null): array
    {
        $page = $this->page($slug);

        if ($page === null) {
            return ['total' => 0, 'rows' => []];
        }

        $query = $page->revisions()->select(['n', 'action', 'by', 'section_count', 'created_at']);

        if ($before !== null) {
            $query->where('n', '<', $before);
        }

        return [
            'total' => $page->revisions()->count(),
            'rows' => $query->limit($limit)->get()->map->toSummaryRow()->all(),
        ];
    }

    public function forget(string $slug): void
    {
        Cache::forget($this->cacheKey($slug));
    }

    private function cacheKey(string $slug): string
    {
        return 'page:'.str_replace('/', ':', $slug).':published';
    }

    private function insert(string $slug, string $title, array $content, string $by): Page
    {
        return DB::transaction(fn () => Page::create([
            'cms_id' => (int) Page::query()->lockForUpdate()->max('cms_id') + 1,
            'slug' => $slug,
            'url' => '/'.$slug,
            'title' => $title,
            'status' => 'draft',
            'seo' => $content['seo'],
            'draft' => $content['draft'],
            'published' => [],
            'last_updated_by' => $by,
        ]));
    }

    private function availableSlug(string $base): string
    {
        $candidate = $base;
        $n = 1;

        while (Page::where('slug', $candidate)->exists()) {
            $n++;
            $candidate = "{$base}-{$n}";
        }

        return $candidate;
    }

    private function page(string $slug): ?Page
    {
        return Page::where('slug', $slug)->first();
    }

    private function addRevision(Page $page, array $sections, string $by): void
    {
        DB::transaction(function () use ($page, $sections, $by) {
            Page::where('id', $page->id)->lockForUpdate()->first();

            PageRevision::create([
                'page_id' => $page->id,
                'n' => (int) $page->revisions()->max('n') + 1,
                'action' => 'publish',
                'by' => $by,
                'section_count' => count($sections),
                'sections' => $sections,
            ]);
        });

        $page->unsetRelation('revisions');
    }

    private function renderable(array $sections, ?array $allowed = null, int $depth = 0): array
    {
        return $this->legal($sections, $allowed, $depth, true);
    }

    private function legal(array $sections, ?array $allowed = null, int $depth = 0, bool $dropInactive = false): array
    {
        $allowed ??= self::SECTION_TYPES;
        $visible = [];

        foreach ($sections as $section) {
            if ($dropInactive && ($section['active'] ?? true) === false) {
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
                $section['children'] = $this->legal(
                    $section['children'] ?? [],
                    self::CHILD_TYPES[$type] ?? [],
                    $next,
                    $dropInactive,
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
