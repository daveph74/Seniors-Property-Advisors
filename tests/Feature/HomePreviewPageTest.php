<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The page is data, not code: it reaches a site by being imported, so the test imports it the same way.
 */
class HomePreviewPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->post('/cms/pages/import', [
            'file' => UploadedFile::fake()->createWithContent(
                'home-preview.page.json',
                file_get_contents(base_path('tests/fixtures/pages/home-preview.page.json')),
            ),
        ])->assertSessionHasNoErrors();

        $this->post('/cms/pages/'.Page::where('slug', 'home-preview')->value('cms_id').'/publish-now');
    }

    public function test_it_is_built_from_the_builder_s_own_blocks(): void
    {
        $this->get('/home-preview')->assertOk()->assertInertia(function (AssertableInertia $page) {
            $page->component('AgentFinder');

            $types = [];
            $walk = function (array $blocks) use (&$walk, &$types) {
                foreach ($blocks as $block) {
                    $types[] = $block['type'];
                    $walk($block['children'] ?? []);
                }
            };
            $walk($page->toArray()['props']['sections']);

            $this->assertContains('finder-start', $types);
            $this->assertContains('checklist', $types);
            $this->assertContains('image', $types);
            $this->assertContains('divider', $types);
        });
    }

    public function test_it_is_reachable_only_by_its_address(): void
    {
        $this->get('/home-preview')->assertSee('noindex', false);
        $this->get('/sitemap.xml')->assertDontSee(url('/home-preview'), false);

        $this->assertStringNotContainsString('home-preview', json_encode(Setting::find('globals')->value));
    }

    public function test_it_is_not_part_of_the_code(): void
    {
        $this->assertFileDoesNotExist(resource_path('content/pages/home-preview.json'));
    }
}
