<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\PageRevision;
use App\Models\Setting;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ContentImportTest extends TestCase
{
    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/content'));

        parent::tearDown();
    }

    private function writeOverlay(array $document, array $revisions = []): void
    {
        $root = storage_path('app/content');

        File::ensureDirectoryExists("{$root}/pages");
        File::put("{$root}/pages/{$document['slug']}.json", json_encode($document));

        if ($revisions !== []) {
            File::ensureDirectoryExists("{$root}/revisions");
            File::put("{$root}/revisions/{$document['slug']}.json", json_encode($revisions));
        }
    }

    private function document(string $slug, string $title): array
    {
        return [
            'slug' => $slug,
            'url' => "/{$slug}",
            'cmsId' => $slug === 'home' ? 1 : 7,
            'title' => $title,
            'status' => 'published',
            'seo' => [],
            'draft' => null,
            'published' => [],
            'last_updated_by' => 'Overlay',
        ];
    }

    public function test_it_skips_existing_pages_unless_forced(): void
    {
        $this->writeOverlay($this->document('home', 'Overlay title'));

        $this->artisan('content:import')->assertSuccessful();
        $this->assertNotSame('Overlay title', Page::where('slug', 'home')->value('title'));

        $this->artisan('content:import', ['--force' => true])->assertSuccessful();
        $this->assertSame('Overlay title', Page::where('slug', 'home')->value('title'));
    }

    public function test_it_imports_new_pages_and_is_idempotent(): void
    {
        $this->writeOverlay($this->document('about', 'About us'));

        $this->artisan('content:import')->assertSuccessful();
        $this->artisan('content:import')->assertSuccessful();

        $this->assertSame(2, Page::count());
        $this->assertSame('About us', Page::where('slug', 'about')->value('title'));
    }

    public function test_it_preserves_original_revision_timestamps_and_does_not_duplicate_them(): void
    {
        $this->writeOverlay($this->document('about', 'About us'), [
            ['n' => 2, 'action' => 'publish', 'by' => 'Second', 'at' => '2026-02-02T10:00:00+00:00', 'sections' => []],
            ['n' => 1, 'action' => 'publish', 'by' => 'First', 'at' => '2026-01-01T10:00:00+00:00', 'sections' => []],
        ]);

        $this->artisan('content:import')->assertSuccessful();
        $this->artisan('content:import', ['--force' => true])->assertSuccessful();

        $page = Page::where('slug', 'about')->first();

        $this->assertSame(2, PageRevision::where('page_id', $page->id)->count());
        $this->assertSame([2, 1], $page->revisions->pluck('n')->all());
        $this->assertSame(
            '2026-01-01T10:00:00+00:00',
            PageRevision::where('page_id', $page->id)->where('n', 1)->value('created_at')->toIso8601String(),
        );
    }

    public function test_it_imports_globals_when_present(): void
    {
        File::ensureDirectoryExists(storage_path('app/content'));
        File::put(storage_path('app/content/globals.json'), json_encode(['phone' => '1300 000 000']));

        $this->artisan('content:import')->assertSuccessful();

        $this->assertSame('1300 000 000', Setting::find('globals')->value['phone']);
    }

    public function test_it_reports_nothing_to_import_without_an_overlay(): void
    {
        File::deleteDirectory(storage_path('app/content'));

        $this->artisan('content:import')->assertSuccessful();

        $this->assertSame(1, Page::count());
    }
}
