<?php

namespace Tests\Feature;

use App\Content\PageContentStore;
use App\Models\Page;
use App\Models\ReusableSection;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * A Rich text block's body is the one field in a section tree that may hold markup. Every other
 * string is stripped of tags, and this file is what keeps that boundary exactly where it is.
 */
class RichTextBodyTest extends TestCase
{
    private function section(array $children): array
    {
        return [[
            'id' => 'section-1',
            'type' => 'section',
            'label' => 'Section',
            'active' => true,
            'anchor' => null,
            'data' => ['width' => 'standard'],
            'children' => $children,
        ]];
    }

    private function block(string $type, array $data, string $id = 'block-1'): array
    {
        return [
            'id' => $id,
            'type' => $type,
            'label' => 'Block',
            'active' => true,
            'anchor' => null,
            'data' => $data,
        ];
    }

    private function saved(array $children): array
    {
        $this->post('/cms/pages/1/draft', ['sections' => $this->section($children)])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        return (new PageContentStore)->document('home')['draft'][0]['children'];
    }

    private function body(string $html): string
    {
        return $this->saved([$this->block('rich-text', ['body' => $html, 'align' => 'left'])])[0]['data']['body'];
    }

    public function test_bold_italic_lists_and_links_survive_in_a_rich_text_body(): void
    {
        $stored = $this->body(
            '<p>Some <strong>bold</strong> and <em>italic</em> words with <a href="https://example.com/guide">a link</a>.</p>'
            .'<ul><li>One</li><li>Two</li></ul><ol><li>First</li></ol>'
        );

        foreach (['<strong>bold</strong>', '<em>italic</em>', 'href="https://example.com/guide"', '<ul><li>One</li>', '<ol><li>First</li></ol>'] as $expected) {
            $this->assertStringContainsString($expected, $stored);
        }
    }

    public function test_script_event_handlers_and_javascript_links_are_stripped_from_a_rich_text_body(): void
    {
        $stored = $this->body(
            '<p><strong onclick="alert(1)">Bold</strong><script>alert(1)</script><a href="javascript:alert(1)">x</a></p>'
        );

        $this->assertStringContainsString('<strong>Bold</strong>', $stored);
        $this->assertStringNotContainsString('<script', $stored);
        $this->assertStringNotContainsString('onclick', $stored);
        $this->assertStringNotContainsString('javascript:', $stored);
    }

    public function test_a_link_opening_a_new_tab_carries_noopener(): void
    {
        $stored = $this->body('<p><a href="https://example.com" target="_blank">Out</a></p>');

        $this->assertStringContainsString('target="_blank"', $stored);
        $this->assertStringContainsString('noopener', $stored);
    }

    public function test_headings_images_and_tables_are_not_allowed_in_a_rich_text_body(): void
    {
        $stored = $this->body(
            '<h2>Heading</h2><p>Text</p><img src="/media/2026/07/a.png" alt=""><img src="https://elsewhere.test/x.png">'
            .'<table><tr><td>Cell</td></tr></table>'
        );

        $this->assertStringContainsString('Heading', $stored);
        $this->assertStringNotContainsString('<h2', $stored);
        $this->assertStringNotContainsString('<img', $stored);
        $this->assertStringNotContainsString('/media/', $stored);
        $this->assertStringNotContainsString('<table', $stored);
    }

    public function test_the_same_markup_anywhere_else_is_stripped_whole(): void
    {
        $markup = '<strong>Bold</strong><a href="https://example.com">link</a>';

        $children = $this->saved([
            $this->block('cta', ['body' => $markup, 'heading' => $markup], 'cta-1'),
            $this->block('text-image', ['body' => $markup], 'text-image-1'),
            $this->block('heading', ['heading' => $markup], 'heading-1'),
            $this->block('rich-text', ['body' => 'Plain', 'align' => $markup], 'rich-text-1'),
        ]);

        foreach ($children as $child) {
            foreach ($child['data'] as $value) {
                $this->assertStringNotContainsString('<', $value, "{$child['type']} kept markup");
            }
        }
    }

    public function test_a_plain_text_body_is_stored_untouched(): void
    {
        $plain = "One & two\n\nThree";

        $this->assertSame($plain, $this->body($plain));
    }

    public function test_an_imported_page_file_purifies_a_rich_text_body_the_same_way(): void
    {
        $document = [
            'format' => PageContentStore::TRANSFER_FORMAT,
            'version' => 1,
            'slug' => 'moved-page',
            'title' => 'Moved page',
            'navLabel' => 'Moved',
            'seo' => ['description' => 'A page that came from another site.', 'noindex' => true],
            'sections' => $this->section([
                $this->block('rich-text', ['body' => '<p><strong>Bold</strong><script>alert(1)</script></p>']),
            ]),
        ];

        $this->from('/cms/pages')->post('/cms/pages/import', [
            'file' => UploadedFile::fake()->createWithContent('page.page.json', json_encode($document)),
        ])->assertSessionHasNoErrors();

        $body = Page::where('slug', 'moved-page')->firstOrFail()->draft[0]['children'][0]['data']['body'];

        $this->assertStringContainsString('<strong>Bold</strong>', $body);
        $this->assertStringNotContainsString('<script', $body);
    }

    public function test_a_reusable_section_purifies_a_rich_text_body_the_same_way(): void
    {
        $this->post('/cms/reusable-sections', [
            'name' => 'Promo',
            'sections' => $this->section([
                $this->block('rich-text', ['body' => '<p><em>Soft</em><script>alert(1)</script></p>']),
            ]),
        ])->assertRedirect();

        $body = ReusableSection::first()->block['children'][0]['data']['body'];

        $this->assertStringContainsString('<em>Soft</em>', $body);
        $this->assertStringNotContainsString('<script', $body);
    }

    public function test_seeded_rich_text_bodies_are_plain_text_so_they_still_render_as_paragraphs(): void
    {
        $bodies = 0;

        foreach (Page::all() as $page) {
            foreach ([$page->published ?? [], $page->draft ?? []] as $tree) {
                $bodies += $this->assertPlainBodies($tree);
            }
        }

        $this->assertGreaterThan(0, $bodies);
    }

    private function assertPlainBodies(array $tree): int
    {
        $count = 0;

        foreach ($tree as $node) {
            if (($node['type'] ?? null) === 'rich-text') {
                $this->assertStringNotContainsString('<', (string) ($node['data']['body'] ?? ''));
                $count++;
            }

            $count += $this->assertPlainBodies($node['children'] ?? []);
        }

        return $count;
    }
}
