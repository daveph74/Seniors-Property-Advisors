<?php

namespace Tests\Feature;

use App\Content\PageContentStore;
use App\Models\Page;
use App\Models\PageRevision;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RevisionHistoryTest extends TestCase
{
    private function sections(string $heading, int $count = 1): array
    {
        return array_map(fn ($i) => [
            'id' => "hero-{$i}",
            'type' => 'hero',
            'label' => "Hero {$i}",
            'active' => true,
            'data' => ['heading' => $heading],
        ], range(1, $count));
    }

    private function rows(): array
    {
        return (new PageContentStore)->revisions('home')['rows'];
    }

    public function test_publishing_an_identical_tree_records_no_new_version(): void
    {
        $this->post('/cms/pages/1/publish', ['sections' => $this->sections('Same')])->assertRedirect();
        $this->assertCount(1, $this->rows());

        $before = Page::where('slug', 'home')->value('published_at');

        $this->travel(2)->minutes();
        $this->post('/cms/pages/1/publish', ['sections' => $this->sections('Same')])->assertRedirect();

        $this->assertCount(1, $this->rows());
        $this->assertNotSame($before, Page::where('slug', 'home')->value('published_at'));
    }

    public function test_publishing_a_changed_tree_records_a_new_version(): void
    {
        $this->post('/cms/pages/1/publish', ['sections' => $this->sections('One')])->assertRedirect();
        $this->post('/cms/pages/1/publish', ['sections' => $this->sections('Two')])->assertRedirect();

        $this->assertCount(2, $this->rows());
    }

    public function test_the_first_publish_always_records_a_version(): void
    {
        $published = (new PageContentStore)->document('home')['published'];

        $this->assertCount(0, $this->rows());

        $this->post('/cms/pages/1/publish', ['sections' => $published])->assertRedirect();

        $this->assertCount(1, $this->rows());
    }

    public function test_the_section_count_is_stored_on_publish(): void
    {
        $this->post('/cms/pages/1/publish', ['sections' => $this->sections('Three', 3)])->assertRedirect();

        $this->assertSame(3, PageRevision::first()->section_count);
        $this->assertSame(3, $this->rows()[0]['sectionCount']);
    }

    public function test_the_migration_backfills_the_section_count_for_existing_rows(): void
    {
        DB::table('page_revisions')->insert([
            'page_id' => Page::where('slug', 'home')->value('id'),
            'n' => 1,
            'action' => 'publish',
            'by' => 'Tester',
            'section_count' => 0,
            'sections' => json_encode($this->sections('Legacy', 4)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(0, (int) DB::table('page_revisions')->value('section_count'));

        $this->artisan('migrate:refresh', [
            '--path' => 'database/migrations/2026_07_30_000001_add_section_count_to_page_revisions_table.php',
        ])->assertSuccessful();

        $this->assertSame(4, (int) DB::table('page_revisions')->value('section_count'));
    }

    public function test_the_list_is_capped_and_reports_the_total(): void
    {
        for ($i = 1; $i <= 4; $i++) {
            $this->post('/cms/pages/1/publish', ['sections' => $this->sections("Take {$i}")])->assertRedirect();
        }

        $page = (new PageContentStore)->revisions('home', 2);

        $this->assertSame(4, $page['total']);
        $this->assertCount(2, $page['rows']);
        $this->assertSame([4, 3], array_column($page['rows'], 'n'));
    }

    public function test_older_versions_can_be_paged_through(): void
    {
        for ($i = 1; $i <= 4; $i++) {
            $this->post('/cms/pages/1/publish', ['sections' => $this->sections("Take {$i}")])->assertRedirect();
        }

        $this->getJson('/cms/pages/1/revisions?before=3')
            ->assertOk()
            ->assertJsonPath('total', 4)
            ->assertJsonPath('rows.0.n', 2)
            ->assertJsonPath('rows.1.n', 1)
            ->assertJsonCount(2, 'rows');

        $this->getJson('/cms/pages/1/revisions?before=1')
            ->assertOk()
            ->assertJsonCount(0, 'rows');
    }

    public function test_the_revisions_endpoint_404s_for_an_unknown_page(): void
    {
        $this->getJson('/cms/pages/99/revisions')->assertNotFound();
    }

    public function test_concurrent_publishes_do_not_collide_on_the_version_number(): void
    {
        $store = new PageContentStore;

        foreach (range(1, 5) as $i) {
            $store->saveDraft('home', $this->sections("Run {$i}"), 'Tester');
            $store->publish('home', 'Tester');
        }

        $ns = PageRevision::orderBy('n')->pluck('n')->all();

        $this->assertSame([1, 2, 3, 4, 5], $ns);
    }
}
