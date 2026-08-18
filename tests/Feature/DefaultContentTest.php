<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DefaultContentTest extends TestCase
{
    private function migration(): object
    {
        return require database_path('migrations/2026_07_31_000002_seed_default_home_page.php');
    }

    public function test_migrations_alone_leave_a_home_page_with_content(): void
    {
        $home = Page::where('slug', 'home')->firstOrFail();

        $this->assertSame('/', $home->url);
        $this->assertSame('published', $home->status);
        $this->assertNotEmpty($home->published);
    }

    public function test_it_fills_a_home_page_that_is_empty(): void
    {
        DB::table('pages')->where('slug', 'home')->update(['published' => '[]', 'draft' => null]);

        $this->migration()->up();

        $this->assertNotEmpty(Page::where('slug', 'home')->firstOrFail()->published);
    }

    public function test_it_recreates_a_home_page_that_is_missing(): void
    {
        DB::table('pages')->where('slug', 'home')->delete();

        $this->migration()->up();

        $this->assertNotEmpty(Page::where('slug', 'home')->firstOrFail()->published);
    }

    public function test_it_never_overwrites_content_someone_has_edited(): void
    {
        DB::table('pages')->where('slug', 'home')->update([
            'title' => 'Edited by the client',
            'published' => json_encode([['id' => 'only', 'type' => 'heading']]),
        ]);

        $this->migration()->up();

        $home = Page::where('slug', 'home')->firstOrFail();

        $this->assertSame('Edited by the client', $home->title);
        $this->assertCount(1, $home->published);
    }

    public function test_it_leaves_an_unpublished_draft_alone(): void
    {
        DB::table('pages')->where('slug', 'home')->update([
            'published' => '[]',
            'draft' => json_encode([['id' => 'wip', 'type' => 'heading']]),
        ]);

        $this->migration()->up();

        $home = Page::where('slug', 'home')->firstOrFail();

        $this->assertCount(1, $home->draft);
        $this->assertSame([], $home->published);
    }

    public function test_rolling_back_does_not_delete_the_content(): void
    {
        $this->migration()->down();

        $this->assertNotEmpty(Page::where('slug', 'home')->firstOrFail()->published);
    }
}
