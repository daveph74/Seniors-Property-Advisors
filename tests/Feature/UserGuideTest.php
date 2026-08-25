<?php

namespace Tests\Feature;

use App\Console\Commands\BuildUserGuide;
use Tests\TestCase;

/**
 * The guide exists twice — as markdown, which is what anybody edits, and as the HTML page staff are
 * actually given. Two copies of anything drift, and this pair would drift in the worst direction:
 * silently, and towards the copy nobody reads being the correct one. A sentence fixed in the markdown
 * would go on being wrong on the page for as long as nobody happened to rebuild it.
 *
 * So this is the same shape as FindMyAgentOptionsParityTest, and for the same reason.
 */
class UserGuideTest extends TestCase
{
    public function test_the_built_page_is_what_the_markdown_currently_says(): void
    {
        $this->artisan('docs:guide --check')
            ->assertSuccessful();
    }

    public function test_both_files_are_where_the_command_thinks_they_are(): void
    {
        $this->assertFileExists(base_path(BuildUserGuide::SOURCE));
        $this->assertFileExists(base_path(BuildUserGuide::OUTPUT));
    }
}
