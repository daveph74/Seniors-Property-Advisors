<?php

namespace Tests\Feature;

use App\Content\PageContentStore;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class SectionFieldsTest extends TestCase
{
    private function block(string $type, array $data): array
    {
        return [[
            'id' => "{$type}-1",
            'type' => $type,
            'label' => 'Block',
            'active' => true,
            'anchor' => null,
            'data' => $data,
        ]];
    }

    private function publish(array $sections): array
    {
        $this->post('/cms/pages/1/publish', ['sections' => $sections])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        return (new PageContentStore)->document('home')['published'][0]['data'];
    }

    public function test_a_hero_persists_its_nested_content(): void
    {
        $data = $this->publish($this->block('hero', [
            'heading' => 'Independent advice',
            'image' => ['src' => '/images/hero.jpg', 'alt' => 'An advisor and a couple'],
            'rating' => ['stars' => '★★★★★', 'label' => 'Rated 4.9 / 5', 'note' => 'by 1,800 homeowners'],
            'ratingCard' => ['label' => 'Rated 4.9 / 5', 'note' => 'Trusted locally'],
            'savingCard' => ['label' => 'Average saving', 'value' => '$11.4k', 'note' => 'Across our clients'],
        ]));

        $this->assertSame('/images/hero.jpg', $data['image']['src']);
        $this->assertSame('An advisor and a couple', $data['image']['alt']);
        $this->assertSame('★★★★★', $data['rating']['stars']);
        $this->assertSame('by 1,800 homeowners', $data['rating']['note']);
        $this->assertSame('Trusted locally', $data['ratingCard']['note']);
        $this->assertSame('$11.4k', $data['savingCard']['value']);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('sections.0.data.image.src', '/images/hero.jpg'));
    }

    public function test_a_full_bleed_hero_persists_its_background_and_buttons(): void
    {
        $data = $this->publish($this->block('hero-full', [
            'eyebrow' => 'Finding your agent',
            'heading' => 'Find the right agent,',
            'headingEm' => 'without the stress.',
            'lead' => 'Independent guidance at every step.',
            'image' => ['src' => '/images/full.jpg', 'alt' => 'An advisor with a couple'],
            'ctas' => [
                ['label' => 'Find My Agent', 'variant' => 'secondary', 'action' => 'open-finder', 'arrow' => true],
                ['label' => 'Speak to an Advisor', 'variant' => 'ghost', 'href' => '/contact', 'onNavy' => true],
            ],
        ]));

        $this->assertSame('/images/full.jpg', $data['image']['src']);
        $this->assertSame('An advisor with a couple', $data['image']['alt']);
        $this->assertSame('without the stress.', $data['headingEm']);
        $this->assertSame('open-finder', $data['ctas'][0]['action']);
        $this->assertTrue($data['ctas'][1]['onNavy']);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('sections.0.data.image.src', '/images/full.jpg'));
    }

    public function test_a_header_button_persists_including_its_arrow_flag(): void
    {
        $data = $this->publish($this->block('trust-cards', [
            'heading' => 'Why us',
            'cta' => ['label' => 'Read more', 'href' => '/about', 'action' => '', 'arrow' => true],
            'items' => [],
        ]));

        $this->assertSame('Read more', $data['cta']['label']);
        $this->assertSame('/about', $data['cta']['href']);
        $this->assertTrue($data['cta']['arrow']);
    }

    public function test_agent_compare_persists_its_labels_sort_and_filter_flags(): void
    {
        $data = $this->publish($this->block('agent-compare', [
            'heading' => 'Compare',
            'sort' => 'Sort: Advisor pick',
            'labels' => [
                'shortlist' => 'Your shortlist', 'experience' => 'Local experience',
                'sales' => 'Recent sales', 'commission' => 'Commission',
                'marketing' => 'Marketing', 'notes' => 'Notes', 'next' => 'Next step',
            ],
            'filters' => [['label' => '3 agents', 'active' => false, 'removable' => false, 'count' => true]],
            'agents' => [['name' => 'Sarah', 'experience' => ['strong' => '12 years', 'meter' => 70]]],
        ]));

        $this->assertCount(7, $data['labels']);
        $this->assertSame('Next step', $data['labels']['next']);
        $this->assertSame('Sort: Advisor pick', $data['sort']);
        $this->assertTrue($data['filters'][0]['count']);
        $this->assertSame(70, $data['agents'][0]['experience']['meter']);
    }

    public function test_a_family_section_persists_its_image_and_testimonial(): void
    {
        $data = $this->publish($this->block('family', [
            'heading' => 'Helping a parent',
            'image' => ['src' => '/images/family.jpg', 'alt' => 'Mother and daughter'],
            'testimonial' => ['quote' => 'Mum felt heard.', 'by' => 'Rachel', 'avatar' => '/images/rachel.jpg'],
            'checks' => [],
            'ctas' => [],
        ]));

        $this->assertSame('/images/family.jpg', $data['image']['src']);
        $this->assertSame('Mum felt heard.', $data['testimonial']['quote']);
        $this->assertSame('/images/rachel.jpg', $data['testimonial']['avatar']);
    }

    public function test_a_why_list_persists_its_image_and_stamp(): void
    {
        $data = $this->publish($this->block('why-list', [
            'heading' => 'Why',
            'image' => ['src' => '/images/home.jpg', 'alt' => 'A home'],
            'stamp' => ['value' => '30+', 'text' => 'years of experience'],
            'items' => [],
        ]));

        $this->assertSame('/images/home.jpg', $data['image']['src']);
        $this->assertSame('30+', $data['stamp']['value']);
        $this->assertSame('years of experience', $data['stamp']['text']);
    }

    public function test_a_call_to_action_persists_its_chosen_background(): void
    {
        $data = $this->publish($this->block('cta', [
            'heading' => 'Ready?',
            'body' => 'Get in touch.',
            'background' => 'image',
            'image' => ['src' => '/images/cta.jpg', 'alt' => 'An advisor and a couple talking'],
            'buttons' => [],
            'trustMarks' => [],
        ]));

        $this->assertSame('image', $data['background']);
        $this->assertSame('/images/cta.jpg', $data['image']['src']);
        $this->assertSame('An advisor and a couple talking', $data['image']['alt']);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('sections.0.data.background', 'image')
                ->where('sections.0.data.image.src', '/images/cta.jpg'));
    }

    public function test_a_call_to_action_can_be_switched_to_the_light_background(): void
    {
        $data = $this->publish($this->block('cta', [
            'heading' => 'Ready?',
            'body' => 'Get in touch.',
            'background' => 'white',
            'buttons' => [],
            'trustMarks' => [],
        ]));

        $this->assertSame('white', $data['background']);
    }

    /** Everything written before the field existed has no `background` key at all. */
    public function test_a_call_to_action_without_a_choice_is_left_as_it_was(): void
    {
        $data = $this->publish($this->block('cta', [
            'heading' => 'Ready?',
            'body' => 'Get in touch.',
            'buttons' => [],
            'trustMarks' => [],
        ]));

        $this->assertArrayNotHasKey('background', $data);
    }

    public function test_a_cta_button_persists_the_dark_background_flag(): void
    {
        $data = $this->publish($this->block('cta', [
            'heading' => 'Ready?',
            'body' => 'Get in touch.',
            'buttons' => [['label' => 'Call us', 'href' => 'tel:1300', 'onNavy' => true, 'arrow' => false]],
            'trustMarks' => [],
        ]));

        $this->assertTrue($data['buttons'][0]['onNavy']);
        $this->assertFalse($data['buttons'][0]['arrow']);
    }

    public function test_a_button_block_persists_its_action_and_a_switched_off_arrow(): void
    {
        $sections = [[
            'id' => 'section-1',
            'type' => 'section',
            'label' => 'Section',
            'active' => true,
            'anchor' => null,
            'data' => ['width' => 'standard'],
            'children' => [[
                'id' => 'row-1', 'type' => 'row', 'label' => 'Row', 'active' => true, 'anchor' => null, 'data' => [],
                'children' => [[
                    'id' => 'col-1', 'type' => 'column', 'label' => 'Column', 'active' => true, 'anchor' => null, 'data' => [],
                    'children' => [[
                        'id' => 'button-1', 'type' => 'button', 'label' => 'Button', 'active' => true, 'anchor' => null,
                        'data' => ['label' => 'Find My Agent', 'href' => '', 'action' => 'open-finder', 'arrow' => false],
                    ]],
                ]],
            ]],
        ]];

        $this->post('/cms/pages/1/publish', ['sections' => $sections])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $button = (new PageContentStore)->document('home')['published'][0]['children'][0]['children'][0]['children'][0];

        $this->assertSame('open-finder', $button['data']['action']);
        $this->assertFalse($button['data']['arrow']);
    }

    public function test_an_info_card_persists_its_style(): void
    {
        $data = $this->publish($this->block('info-card', [
            'cardStyle' => 'saving',
            'title' => 'Average saving',
            'value' => '$11.4k',
            'note' => 'Across our clients',
        ]));

        $this->assertSame('saving', $data['cardStyle']);
    }
}
