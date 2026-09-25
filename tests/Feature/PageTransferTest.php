<?php

namespace Tests\Feature;

use App\Content\PageContentStore;
use App\Models\Media;
use App\Models\Page;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PageTransferTest extends TestCase
{
    private function document(array $overrides = []): array
    {
        return array_replace([
            'format' => PageContentStore::TRANSFER_FORMAT,
            'version' => 1,
            'slug' => 'moved-page',
            'title' => 'Moved page',
            'navLabel' => 'Moved',
            'seo' => ['description' => 'A page that came from another site.', 'noindex' => true],
            'sections' => [[
                'id' => 'section-a',
                'type' => 'section',
                'label' => 'Section',
                'active' => true,
                'anchor' => null,
                'data' => ['background' => 'navy-gradient', 'textTheme' => 'light'],
                'children' => [[
                    'id' => 'heading-a',
                    'type' => 'heading',
                    'label' => 'Heading',
                    'active' => true,
                    'anchor' => null,
                    'data' => ['heading' => 'Hello from elsewhere', 'level' => 'h2'],
                ]],
            ]],
        ], $overrides);
    }

    private function upload(array|string $document): TestResponse
    {
        $body = is_string($document) ? $document : json_encode($document);

        return $this->from('/cms/pages')->post('/cms/pages/import', [
            'file' => UploadedFile::fake()->createWithContent('page.page.json', $body),
        ]);
    }

    public function test_an_export_carries_the_page_and_nothing_that_belongs_to_this_site(): void
    {
        $home = Page::where('slug', 'home')->firstOrFail();

        $response = $this->get("/cms/pages/{$home->cms_id}/export")->assertOk();

        $this->assertStringContainsString('attachment; filename="home.page.json"', $response->headers->get('Content-Disposition'));

        $document = $response->json();

        $this->assertSame(['format', 'version', 'slug', 'title', 'navLabel', 'seo', 'sections'], array_keys($document));
        $this->assertSame($home->published, $document['sections']);
    }

    public function test_an_export_takes_the_draft_when_there_is_one(): void
    {
        $home = Page::where('slug', 'home')->firstOrFail();
        $draft = $this->document()['sections'];
        $home->forceFill(['draft' => $draft])->save();

        $this->assertSame($draft, $this->get("/cms/pages/{$home->cms_id}/export")->json('sections'));
    }

    public function test_an_exported_page_imports_elsewhere_as_the_same_tree(): void
    {
        $home = Page::where('slug', 'home')->firstOrFail();
        $document = $this->get("/cms/pages/{$home->cms_id}/export")->json();

        $this->upload(['slug' => 'home-copy-from-file'] + $document)->assertSessionHasNoErrors();

        $this->assertSame($home->published, Page::where('slug', 'home-copy-from-file')->value('draft'));
    }

    public function test_an_imported_page_is_a_draft_nobody_can_see_yet(): void
    {
        $this->upload($this->document())
            ->assertRedirect('/cms/pages')
            ->assertSessionHas('imported', fn (array $imported) => $imported['url'] === '/moved-page');

        $page = Page::where('slug', 'moved-page')->firstOrFail();

        $this->assertSame('draft', $page->status);
        $this->assertSame([], $page->published);
        $this->assertSame('Moved', $page->nav_label);
        $this->assertSame('A page that came from another site.', $page->seo['description']);

        auth()->logout();
        $this->get('/moved-page')->assertNotFound();
    }

    public function test_an_address_that_is_taken_is_refused_and_nothing_is_created(): void
    {
        $before = Page::count();
        $home = Page::where('slug', 'home')->value('published');

        $this->upload($this->document(['slug' => 'contact']))
            ->assertSessionHasErrors(['document.slug' => 'A page at /contact already exists on this site, so nothing was imported. Rename that page first if this one should replace it.']);

        $this->assertSame($before, Page::count());
        $this->assertSame($home, Page::where('slug', 'home')->value('published'));
    }

    public function test_a_reserved_address_is_refused(): void
    {
        $this->upload($this->document(['slug' => 'cms']))->assertSessionHasErrors('document.slug');
        $this->upload($this->document(['slug' => 'blog/sneaky']))->assertSessionHasErrors('document.slug');
    }

    public function test_a_page_under_a_parent_this_site_lacks_is_refused(): void
    {
        $this->upload($this->document(['slug' => 'nowhere/child']))->assertSessionHasErrors('document.slug');
    }

    public function test_a_block_this_site_cannot_render_is_refused(): void
    {
        $document = $this->document();
        $document['sections'][0]['children'][0]['type'] = 'carousel';

        $this->upload($document)->assertSessionHasErrors('document.sections.0.children.0.type');
        $this->assertNull(Page::where('slug', 'moved-page')->first());
    }

    public function test_rows_nested_too_deep_are_refused(): void
    {
        $row = fn (array $children) => ['id' => uniqid('row-'), 'type' => 'row', 'label' => 'Row', 'active' => true, 'data' => [], 'children' => [
            ['id' => uniqid('column-'), 'type' => 'column', 'label' => 'Column', 'active' => true, 'data' => [], 'children' => $children],
        ]];

        $document = $this->document();
        $document['sections'][0]['children'] = [$row([$row([$row([])])])];

        $this->upload($document)->assertSessionHasErrors();
        $this->assertNull(Page::where('slug', 'moved-page')->first());
    }

    public function test_a_file_that_is_not_a_page_is_refused_in_plain_words(): void
    {
        $this->upload('this is not json')
            ->assertSessionHasErrors(['document' => 'That file isn’t a page exported from this CMS.']);

        $this->upload(['format' => 'something-else'] + $this->document())
            ->assertSessionHasErrors('document');
    }

    public function test_markup_in_the_file_does_not_survive(): void
    {
        $document = $this->document(['title' => 'Moved <script>alert(1)</script>page']);
        $document['sections'][0]['children'][0]['data']['heading'] = 'Hello <img src=x onerror=alert(1)>there';

        $this->upload($document)->assertSessionHasNoErrors();

        $page = Page::where('slug', 'moved-page')->firstOrFail();

        $this->assertStringNotContainsString('<', $page->title);
        $this->assertStringNotContainsString('<', json_encode($page->draft));
    }

    public function test_a_seed_file_is_accepted_too(): void
    {
        $seed = json_decode(file_get_contents(resource_path('content/pages/contact.json')), true);

        $this->upload(['slug' => 'contact-from-seed'] + $seed)->assertSessionHasNoErrors();

        $this->assertSame($seed['published'], Page::where('slug', 'contact-from-seed')->value('draft'));
    }

    public function test_images_this_site_does_not_have_are_named(): void
    {
        Media::create([
            'key' => '2026/08/held.jpg', 'name' => 'held.jpg', 'mime' => 'image/jpeg',
            'size' => 10, 'width' => 10, 'height' => 10, 'disk' => 's3',
        ]);

        $document = $this->document();
        $document['sections'][0]['children'][] = [
            'id' => 'image-a', 'type' => 'image', 'label' => 'Image', 'active' => true,
            'data' => ['src' => '/media/2026/08/held.jpg'],
        ];
        $document['sections'][0]['children'][] = [
            'id' => 'image-b', 'type' => 'image', 'label' => 'Image', 'active' => true,
            'data' => ['src' => '/media/2026/08/missing-photo.jpg'],
        ];

        $this->upload($document)->assertSessionHas('imported', fn (array $imported) => $imported['missingMedia'] === ['missing-photo.jpg']);
    }

    public function test_a_client_administrator_can_download_a_page_but_not_import_one(): void
    {
        $this->actingAs($this->clientAdmin());

        $home = Page::where('slug', 'home')->firstOrFail();
        $this->get("/cms/pages/{$home->cms_id}/export")->assertOk();

        $this->upload($this->document())->assertForbidden();
        $this->assertNull(Page::where('slug', 'moved-page')->first());

        $this->get('/cms/pages')->assertInertia(
            fn ($page) => $this->assertFalse($page->toArray()['props']['auth']['can']['pages.import']),
        );
    }

    public function test_nobody_signed_out_can_do_either(): void
    {
        auth()->logout();

        $this->get('/cms/pages/1/export')->assertRedirect('/login');
        $this->upload($this->document())->assertRedirect('/login');
        $this->assertNull(Page::where('slug', 'moved-page')->first());
    }
}
