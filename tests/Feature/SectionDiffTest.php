<?php

namespace Tests\Feature;

use App\Content\SectionDiff;
use Tests\TestCase;

class SectionDiffTest extends TestCase
{
    private function block(string $id, string $heading = 'One', bool $active = true): array
    {
        return [
            'id' => $id,
            'type' => 'hero',
            'label' => "Block {$id}",
            'active' => $active,
            'data' => ['heading' => $heading],
        ];
    }

    public function test_identical_trees_report_no_change(): void
    {
        $tree = [$this->block('a'), $this->block('b')];

        $diff = SectionDiff::between($tree, $tree);

        $this->assertTrue($diff['unchanged']);
        $this->assertSame([], $diff['added']);
    }

    public function test_it_reports_additions_and_removals(): void
    {
        $diff = SectionDiff::between([$this->block('a')], [$this->block('a'), $this->block('b')]);

        $this->assertSame(['Block b'], $diff['added']);
        $this->assertSame([], $diff['removed']);
        $this->assertFalse($diff['unchanged']);

        $reverse = SectionDiff::between([$this->block('a'), $this->block('b')], [$this->block('a')]);

        $this->assertSame(['Block b'], $reverse['removed']);
    }

    public function test_it_reports_an_edit_without_reporting_a_move(): void
    {
        $diff = SectionDiff::between([$this->block('a', 'One')], [$this->block('a', 'Two')]);

        $this->assertSame(['Block a'], $diff['edited']);
        $this->assertSame([], $diff['moved']);
    }

    public function test_it_reports_a_move_without_reporting_an_edit(): void
    {
        $diff = SectionDiff::between(
            [$this->block('a'), $this->block('b')],
            [$this->block('b'), $this->block('a')],
        );

        $this->assertSame([], $diff['edited']);
        $this->assertEqualsCanonicalizing(['Block a', 'Block b'], $diff['moved']);
    }

    public function test_hiding_a_block_counts_as_an_edit(): void
    {
        $diff = SectionDiff::between([$this->block('a')], [$this->block('a', 'One', false)]);

        $this->assertSame(['Block a'], $diff['edited']);
    }

    public function test_it_walks_into_children(): void
    {
        $before = [['id' => 's', 'type' => 'section', 'label' => 'Section', 'active' => true, 'data' => [],
            'children' => [$this->block('a')]]];
        $after = [['id' => 's', 'type' => 'section', 'label' => 'Section', 'active' => true, 'data' => [],
            'children' => [$this->block('a', 'Changed')]]];

        $diff = SectionDiff::between($before, $after);

        $this->assertSame(['Block a'], $diff['edited']);
    }

    public function test_comparing_a_revision_with_the_live_page(): void
    {
        $this->post('/cms/pages/1/publish', ['sections' => [$this->block('a', 'One')]])->assertRedirect();
        $this->post('/cms/pages/1/publish', ['sections' => [$this->block('a', 'Two')]])->assertRedirect();

        $this->getJson('/cms/pages/1/compare/1')
            ->assertOk()
            ->assertJsonPath('edited', ['Block a'])
            ->assertJsonPath('against', 'live');
    }

    public function test_comparing_two_revisions(): void
    {
        $this->post('/cms/pages/1/publish', ['sections' => [$this->block('a')]])->assertRedirect();
        $this->post('/cms/pages/1/publish', ['sections' => [$this->block('a'), $this->block('b')]])->assertRedirect();

        $this->getJson('/cms/pages/1/compare/1?against=2')
            ->assertOk()
            ->assertJsonPath('added', ['Block b']);
    }

    public function test_the_publish_changes_endpoint_diffs_the_posted_draft(): void
    {
        $this->post('/cms/pages/1/publish', ['sections' => [$this->block('a', 'One')]])->assertRedirect();

        $this->postJson('/cms/pages/1/changes', ['sections' => [$this->block('a', 'Two')]])
            ->assertOk()
            ->assertJsonPath('edited', ['Block a'])
            ->assertJsonPath('unchanged', false);

        $this->postJson('/cms/pages/1/changes', ['sections' => [$this->block('a', 'One')]])
            ->assertOk()
            ->assertJsonPath('unchanged', true);
    }

    public function test_comparing_an_unknown_revision_is_a_404(): void
    {
        $this->getJson('/cms/pages/1/compare/99')->assertNotFound();
    }
}
