<?php

namespace Tests\Feature;

use App\Models\ReusableSection;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ReusableSectionTest extends TestCase
{
    private function block(array $overrides = []): array
    {
        return array_merge([
            'id' => 'section-1',
            'type' => 'section',
            'label' => 'Promo band',
            'active' => true,
            'data' => ['width' => 'standard'],
            'children' => [[
                'id' => 'row-1',
                'type' => 'row',
                'label' => 'Row',
                'active' => true,
                'data' => [],
                'children' => [[
                    'id' => 'col-1',
                    'type' => 'column',
                    'label' => 'Column 1',
                    'active' => true,
                    'data' => [],
                    'children' => [[
                        'id' => 'heading-1',
                        'type' => 'heading',
                        'label' => 'Heading',
                        'active' => true,
                        'data' => ['text' => 'Reusable heading'],
                    ]],
                ]],
            ]],
        ], $overrides);
    }

    public function test_a_subtree_round_trips(): void
    {
        $this->post('/cms/reusable-sections', ['name' => 'Promo band', 'sections' => [$this->block()]])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $saved = ReusableSection::first();

        $this->assertSame('Promo band', $saved->name);
        $this->assertSame('section', $saved->type);
        $this->assertSame('Reusable heading', $saved->block['children'][0]['children'][0]['children'][0]['data']['text']);

        $this->getJson("/cms/reusable-sections/{$saved->id}")
            ->assertOk()
            ->assertJsonPath('children.0.children.0.children.0.data.text', 'Reusable heading');
    }

    public function test_it_rejects_an_illegal_subtree(): void
    {
        $this->post('/cms/reusable-sections', [
            'name' => 'Bad',
            'sections' => [$this->block(['type' => 'column', 'children' => []])],
        ])->assertSessionHasErrors();

        $this->assertSame(0, ReusableSection::count());
    }

    public function test_it_rejects_a_nameless_section(): void
    {
        $this->post('/cms/reusable-sections', ['name' => '', 'sections' => [$this->block()]])
            ->assertSessionHasErrors('name');
    }

    public function test_it_rejects_more_than_one_block(): void
    {
        $this->post('/cms/reusable-sections', [
            'name' => 'Two',
            'sections' => [$this->block(), $this->block(['id' => 'section-2'])],
        ])->assertSessionHasErrors('sections');
    }

    public function test_markup_is_stripped_from_a_saved_section(): void
    {
        $block = $this->block();
        $block['children'][0]['children'][0]['children'][0]['data']['text'] = '<script>x</script>Clean';

        $this->post('/cms/reusable-sections', ['name' => 'Promo', 'sections' => [$block]])->assertRedirect();

        $this->assertSame(
            'xClean',
            ReusableSection::first()->block['children'][0]['children'][0]['children'][0]['data']['text'],
        );
    }

    public function test_saved_sections_reach_the_builder(): void
    {
        $this->post('/cms/reusable-sections', ['name' => 'Promo band', 'sections' => [$this->block()]])->assertRedirect();

        $this->get('/cms/pages/1/edit')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->has('reusables', 1)
                ->where('reusables.0.name', 'Promo band')
                ->where('reusables.0.type', 'section'));
    }

    public function test_a_saved_section_can_be_deleted_without_touching_pages(): void
    {
        $this->post('/cms/reusable-sections', ['name' => 'Promo band', 'sections' => [$this->block()]])->assertRedirect();

        $id = ReusableSection::first()->id;

        $this->post('/cms/pages/1/draft', ['sections' => [$this->block()]])->assertRedirect();

        $this->delete("/cms/reusable-sections/{$id}")->assertRedirect();

        $this->assertSame(0, ReusableSection::count());
        $this->get('/cms/pages/1/edit')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->has('reusables', 0)
                ->where('sections.0.label', 'Promo band'));
    }

    public function test_unknown_saved_sections_are_404(): void
    {
        $this->getJson('/cms/reusable-sections/99')->assertNotFound();
        $this->delete('/cms/reusable-sections/99')->assertNotFound();
    }
}
