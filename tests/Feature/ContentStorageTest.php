<?php

namespace Tests\Feature;

use App\Content\PageContentStore;
use App\Models\Page;
use App\Models\PageRevision;
use Database\Seeders\ContentSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ContentStorageTest extends TestCase
{
    private function tree(): array
    {
        return [
            [
                'id' => 'a1',
                'type' => 'section',
                'label' => 'Séction — ünicode ✓',
                'active' => true,
                'anchor' => null,
                'data' => [],
                'children' => [
                    [
                        'id' => 'b1',
                        'type' => 'row',
                        'label' => 'Row',
                        'active' => true,
                        'anchor' => null,
                        'data' => ['gap' => 24],
                        'children' => [],
                    ],
                    [
                        'id' => 'b2',
                        'type' => 'heading',
                        'label' => 'Heading',
                        'active' => true,
                        'anchor' => null,
                        'data' => ['text' => 'Ünicode — “quoted” ✓'],
                    ],
                ],
            ],
        ];
    }

    public function test_a_published_tree_round_trips_byte_for_byte(): void
    {
        $store = new PageContentStore;
        $tree = $this->tree();

        $store->saveDraft('home', $tree, 'Tester');
        $store->publish('home', 'Tester');

        $document = $store->document('home');

        $this->assertSame($tree, $document['published']);
        $this->assertArrayNotHasKey('children', $document['published'][0]['children'][1]);
        $this->assertSame([], $document['published'][0]['data']);
        $this->assertSame([], $document['published'][0]['children'][0]['children']);
    }

    public function test_draft_round_trips_as_null_not_an_empty_array(): void
    {
        $store = new PageContentStore;

        $this->assertNull($store->document('home')['draft']);

        $store->saveDraft('home', $this->tree(), 'Tester');
        $this->assertNotNull($store->document('home')['draft']);

        $store->publish('home', 'Tester');
        $this->assertNull($store->document('home')['draft']);
    }

    public function test_find_by_cms_id_resolves_known_ids_in_a_single_query(): void
    {
        $store = new PageContentStore;

        DB::enableQueryLog();
        $this->assertSame('home', $store->findByCmsId(1));
        $this->assertCount(1, DB::getQueryLog());
        DB::disableQueryLog();
    }

    public function test_find_by_cms_id_returns_null_for_unknown_and_non_numeric_ids(): void
    {
        $store = new PageContentStore;

        $this->assertNull($store->findByCmsId(99));
        $this->assertNull($store->findByCmsId('abc'));
        $this->assertNull($store->findByCmsId('1; drop table pages'));
    }

    public function test_revisions_are_returned_newest_first_and_numbered_upwards(): void
    {
        $store = new PageContentStore;

        foreach (['one', 'two', 'three'] as $by) {
            $tree = $this->tree();
            $tree[0]['label'] = "Edited by {$by}";

            $store->saveDraft('home', $tree, $by);
            $store->publish('home', $by);
        }

        $revisions = $store->revisions('home')['rows'];

        $this->assertSame([3, 2, 1], array_column($revisions, 'n'));
        $this->assertSame(['three', 'two', 'one'], array_column($revisions, 'by'));
        $this->assertSame(['n', 'action', 'by', 'at', 'sectionCount'], array_keys($revisions[0]));
        $this->assertSame(1, $revisions[0]['sectionCount']);
    }

    public function test_the_revision_list_never_loads_the_section_blobs(): void
    {
        $store = new PageContentStore;
        $store->saveDraft('home', $this->tree(), 'Tester');
        $store->publish('home', 'Tester');

        DB::enableQueryLog();
        $store->revisions('home');
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $listQuery = collect($queries)->firstWhere(fn ($q) => str_contains($q['query'], 'page_revisions')
            && str_contains($q['query'], 'select "n"'));

        $this->assertNotNull($listQuery, 'expected a column-scoped select against page_revisions');
        $this->assertStringNotContainsString('"sections"', $listQuery['query']);
    }

    public function test_revisions_are_empty_for_an_unknown_page(): void
    {
        $this->assertSame(['total' => 0, 'rows' => []], (new PageContentStore)->revisions('nope'));
    }

    public function test_the_seeder_is_idempotent_and_creates_no_revisions(): void
    {
        $this->seed(ContentSeeder::class);
        $this->seed(ContentSeeder::class);

        $this->assertSame(1, Page::count());
        $this->assertSame(0, PageRevision::count());
    }

    public function test_globals_come_from_the_settings_table(): void
    {
        $globals = (new PageContentStore)->globals();

        $this->assertArrayHasKey('nav', $globals);
        $this->assertNotEmpty($globals['nav']['links']);
        $this->assertSame('How it works', $globals['nav']['links'][0]['label']);
    }
}
