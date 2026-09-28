<?php

namespace Tests\Feature;

use App\Models\Page;
use Tests\TestCase;

/**
 * `seo:apply` exists because the obvious way to do this is destructive.
 *
 * The metadata lives in `resources/content/pages/*.json`, which is the source of truth for a fresh
 * install. A live site is not one, and `php artisan db:seed` would `updateOrCreate` every page from the
 * repository — sections included — silently replacing whatever editors have done since launch. So this
 * command writes the two fields it owns and nothing else, and these tests are mostly about the "nothing
 * else".
 */
class ApplySeoMetadataTest extends TestCase
{
    private function page(string $slug = 'glossary'): Page
    {
        return Page::where('slug', $slug)->firstOrFail();
    }

    private function fileMetadata(string $slug = 'glossary'): array
    {
        $document = json_decode(file_get_contents(resource_path("content/pages/{$slug}.json")), true);

        return $document['seo'];
    }

    public function test_it_reports_without_writing_until_told_to(): void
    {
        $page = $this->page();
        $page->update(['seo' => ['title' => 'Something else entirely']]);

        $this->artisan('seo:apply')
            ->expectsOutputToContain('glossary')
            ->assertSuccessful();

        $this->assertSame('Something else entirely', $this->page()->fresh()->seo['title']);
    }

    public function test_it_writes_the_titles_and_descriptions_when_forced(): void
    {
        $this->page()->update(['seo' => ['title' => 'Old', 'description' => 'Old too.']]);

        $this->artisan('seo:apply --force')->assertSuccessful();

        $seo = $this->page()->fresh()->seo;

        $this->assertSame($this->fileMetadata()['title'], $seo['title']);
        $this->assertSame($this->fileMetadata()['description'], $seo['description']);
    }

    /**
     * The whole reason this is not `db:seed`. A page's sections are the editor's work and this command has
     * no business near them.
     */
    public function test_it_does_not_touch_the_sections_or_the_page_title(): void
    {
        $page = $this->page();
        $sections = $page->published;
        $title = $page->title;

        $page->update(['seo' => ['title' => 'Old']]);

        $this->artisan('seo:apply --force')->assertSuccessful();

        $fresh = $this->page()->fresh();

        $this->assertSame($sections, $fresh->published, 'the section tree was rewritten');
        $this->assertSame($title, $fresh->title, 'the page title was rewritten');
    }

    /**
     * A seed file that does not mention the sharing image is not an instruction to remove one. Those are
     * per-page choices an editor makes in the builder, and a metadata pass must leave them alone.
     */
    public function test_it_leaves_an_editors_own_image_and_canonical_alone(): void
    {
        $this->page()->update(['seo' => [
            'title' => 'Old',
            'image' => '/media/2026/08/chosen-by-an-editor.jpg',
            'canonical' => 'https://example.com/elsewhere',
            'noindex' => true,
        ]]);

        $this->artisan('seo:apply --force')->assertSuccessful();

        $seo = $this->page()->fresh()->seo;

        $this->assertSame('/media/2026/08/chosen-by-an-editor.jpg', $seo['image']);
        $this->assertSame('https://example.com/elsewhere', $seo['canonical']);
        $this->assertTrue($seo['noindex']);
        $this->assertSame($this->fileMetadata()['title'], $seo['title']);
    }

    public function test_it_says_nothing_needs_doing_when_the_site_already_agrees(): void
    {
        $this->artisan('seo:apply --force')->assertSuccessful();

        $this->artisan('seo:apply')
            ->expectsOutputToContain('Nothing to change')
            ->assertSuccessful();
    }

    /**
     * A page in the repository that this site has never had is reported and stepped over, not created.
     * Creating one would make this a content-installer, which is `pages:scaffold`'s job.
     */
    public function test_a_page_the_site_does_not_have_is_skipped_rather_than_created(): void
    {
        $before = Page::count();
        Page::where('slug', 'glossary')->delete();

        $this->artisan('seo:apply --force')
            ->expectsOutputToContain('not on this site')
            ->assertSuccessful();

        $this->assertSame($before - 1, Page::count());
        $this->assertNull(Page::where('slug', 'glossary')->first());
    }
}
