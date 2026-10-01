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

    public function test_a_section_persists_its_background_image_and_overlay(): void
    {
        $data = $this->publish($this->block('section', [
            'background' => 'navy',
            'textTheme' => 'light',
            'backgroundImage' => ['src' => '/media/2026/07/skyline.jpg', 'alt' => 'The city at dusk'],
            'backgroundPosition' => 'top-right',
            'overlay' => 'navy-strong',
            'overlayOpacity' => 40,
        ]));

        $this->assertSame('/media/2026/07/skyline.jpg', $data['backgroundImage']['src']);
        $this->assertSame('The city at dusk', $data['backgroundImage']['alt']);
        $this->assertSame('top-right', $data['backgroundPosition']);
        $this->assertSame('navy-strong', $data['overlay']);
        $this->assertSame(40, $data['overlayOpacity']);

        $this->get('/')->assertInertia(fn (AssertableInertia $p) => $p
            ->where('sections.0.data.backgroundImage.src', '/media/2026/07/skyline.jpg')
            ->where('sections.0.data.overlay', 'navy-strong')
            ->where('sections.0.data.overlayOpacity', 40));
    }

    public function test_a_heading_persists_its_line_break_switches(): void
    {
        $data = $this->publish($this->block('heading', [
            'heading' => 'How we',
            'headingEm' => 'help you',
            'headingAfter' => 'sell your home',
            'emOnNewLine' => true,
            'afterOnNewLine' => true,
        ]));

        $this->assertTrue($data['emOnNewLine']);
        $this->assertTrue($data['afterOnNewLine']);
    }

    public function test_a_banner_persists_its_controls(): void
    {
        $data = $this->publish($this->block('banner', [
            'heading' => 'Highest Price.',
            'headingEm' => 'Minimal Stress.',
            'emOnNewLine' => true,
            'lead' => 'Our service is tailored to your needs.',
            'image' => ['src' => '/media/2026/09/wine.jpg', 'alt' => 'Friends at a table'],
            'ctas' => [['label' => "Let's talk", 'href' => '/contact', 'variant' => 'primary', 'onNavy' => true]],
            'height' => 'compact',
            'titleSize' => 'hero',
            'textSize' => 'large',
            'overlay' => 'navy-bottom',
            'overlayOpacity' => 65,
            'imagePosition' => 'bottom-left',
            'align' => 'center',
            'copyWidth' => 'wide',
            'animation' => 'fade-in',
            'animationDelay' => '300',
        ]));

        foreach (['height' => 'compact', 'titleSize' => 'hero', 'textSize' => 'large', 'overlay' => 'navy-bottom', 'overlayOpacity' => 65, 'imagePosition' => 'bottom-left', 'align' => 'center', 'copyWidth' => 'wide', 'animation' => 'fade-in', 'animationDelay' => '300'] as $key => $value) {
            $this->assertSame($value, $data[$key]);
        }

        $this->assertTrue($data['emOnNewLine']);
        $this->assertSame('/media/2026/09/wine.jpg', $data['image']['src']);
        $this->assertSame("Let's talk", $data['ctas'][0]['label']);
    }

    public function test_a_row_persists_its_column_gap(): void
    {
        $section = $this->block('section', ['width' => 'standard']);
        $section[0]['children'] = $this->block('row', ['gap' => 'large', 'spaceAbove' => 'xlarge', 'spaceBelow' => 'small']);

        $data = $this->publish($section);

        $row = (new PageContentStore)->document('home')['published'][0]['children'][0]['data'];

        $this->assertSame('large', $row['gap']);
        $this->assertSame('xlarge', $row['spaceAbove']);
        $this->assertSame('small', $row['spaceBelow']);
        $this->assertSame('standard', $data['width']);
    }

    public function test_a_section_persists_its_tablet_and_mobile_layout(): void
    {
        $section = $this->block('section', [
            'width' => 'standard',
            'height' => 'comfortable',
            'spaceAbove' => 'none',
            'responsive' => [
                'tablet' => ['height' => 'compact', 'spaceAbove' => 'large'],
                'mobile' => ['height' => 'slim'],
            ],
        ]);

        $data = $this->publish($section);

        $this->assertSame('comfortable', $data['height']);
        $this->assertSame('none', $data['spaceAbove']);
        $this->assertSame('compact', $data['responsive']['tablet']['height']);
        $this->assertSame('large', $data['responsive']['tablet']['spaceAbove']);
        $this->assertSame('slim', $data['responsive']['mobile']['height']);
        $this->assertArrayNotHasKey('spaceAbove', $data['responsive']['mobile']);
    }

    public function test_a_column_persists_its_tablet_and_mobile_order(): void
    {
        $section = $this->block('section', ['width' => 'standard']);
        $section[0]['children'] = $this->block('row', ['gap' => 'medium']);
        $section[0]['children'][0]['children'] = [
            ['id' => 'column-1', 'type' => 'column', 'label' => 'Text', 'active' => true, 'anchor' => null, 'data' => ['alignAcross' => 'fill', 'responsive' => ['tablet' => ['order' => '2']]]],
            ['id' => 'column-2', 'type' => 'column', 'label' => 'Image', 'active' => true, 'anchor' => null, 'data' => ['alignAcross' => 'fill', 'responsive' => ['tablet' => ['order' => '1'], 'mobile' => ['order' => '2']]]],
        ];

        $this->publish($section);

        $columns = (new PageContentStore)->document('home')['published'][0]['children'][0]['children'];

        $this->assertSame('Text', $columns[0]['label']);
        $this->assertSame('2', $columns[0]['data']['responsive']['tablet']['order']);
        $this->assertSame('1', $columns[1]['data']['responsive']['tablet']['order']);
        $this->assertSame('2', $columns[1]['data']['responsive']['mobile']['order']);
        $this->assertArrayNotHasKey('order', $columns[0]['data']);
    }

    public function test_a_column_persists_its_width_and_a_tablet_width(): void
    {
        $section = $this->block('section', ['width' => 'standard']);
        $section[0]['children'] = $this->block('row', ['gap' => 'medium']);
        $section[0]['children'][0]['children'] = $this->block('column', [
            'width' => 'two-thirds',
            'responsive' => ['tablet' => ['width' => 'half']],
        ]);

        $this->publish($section);

        $column = (new PageContentStore)->document('home')['published'][0]['children'][0]['children'][0]['data'];

        $this->assertSame('two-thirds', $column['width']);
        $this->assertSame('half', $column['responsive']['tablet']['width']);
    }

    public function test_a_website_section_and_a_column_persist_a_backdrop(): void
    {
        $why = $this->block('why-list', [
            'heading' => 'Why us',
            'background' => 'wash',
            'textTheme' => 'dark',
            'backgroundImage' => ['src' => '/media/2026/09/office.jpg', 'alt' => 'The office'],
            'backgroundPosition' => 'left',
            'overlay' => 'white-left',
        ]);
        $section = $this->block('section', ['width' => 'standard']);
        $section[0]['children'] = $this->block('row', ['gap' => 'medium']);
        $section[0]['children'][0]['children'] = $this->block('column', ['background' => 'navy', 'textTheme' => 'light']);

        $this->publish([...$why, ...$section]);

        $published = (new PageContentStore)->document('home')['published'];

        $this->assertSame('wash', $published[0]['data']['background']);
        $this->assertSame('/media/2026/09/office.jpg', $published[0]['data']['backgroundImage']['src']);
        $this->assertSame('white-left', $published[0]['data']['overlay']);
        $this->assertSame('navy', $published[1]['children'][0]['children'][0]['data']['background']);
        $this->assertSame('light', $published[1]['children'][0]['children'][0]['data']['textTheme']);
    }

    public function test_headings_persist_the_look_they_borrow(): void
    {
        $heading = $this->block('heading', ['heading' => 'Hello', 'level' => 'h2', 'look' => 'h1']);
        $why = $this->block('why-list', ['heading' => 'Why us', 'titleLook' => 'h3']);
        $why[0]['id'] = 'why-1';

        $this->publish([...$heading, ...$why]);

        $published = (new PageContentStore)->document('home')['published'];

        $this->assertSame('h1', $published[0]['data']['look']);
        $this->assertSame('h2', $published[0]['data']['level']);
        $this->assertSame('h3', $published[1]['data']['titleLook']);
    }

    public function test_any_block_persists_an_animation_and_delay(): void
    {
        $heading = $this->block('heading', ['heading' => 'Hello', 'animation' => 'fade-left', 'animationDelay' => '100']);
        $why = $this->block('why-list', ['heading' => 'Why', 'animation' => 'zoom-in', 'animationDelay' => '300']);
        $why[0]['id'] = 'why-1';

        $this->publish([...$heading, ...$why]);

        $published = (new PageContentStore)->document('home')['published'];

        $this->assertSame('fade-left', $published[0]['data']['animation']);
        $this->assertSame('100', $published[0]['data']['animationDelay']);
        $this->assertSame('zoom-in', $published[1]['data']['animation']);
    }

    public function test_a_component_and_a_row_persist_an_animation_per_part(): void
    {
        $trust = $this->block('trust-cards', ['heading' => 'Trust', 'animation' => 'fade-up', 'animationScope' => 'parts']);
        $section = $this->block('section', ['width' => 'standard']);
        $section[0]['id'] = 'section-9';
        $section[0]['children'] = $this->block('row', ['gap' => 'medium', 'animation' => 'fade-in', 'animationScope' => 'parts']);

        $this->publish([...$trust, ...$section]);

        $published = (new PageContentStore)->document('home')['published'];

        $this->assertSame('parts', $published[0]['data']['animationScope']);
        $this->assertSame('parts', $published[1]['children'][0]['data']['animationScope']);
    }

    public function test_the_heroes_and_the_banner_persist_an_animation_per_part(): void
    {
        $hero = $this->block('hero-full', ['heading' => 'Welcome', 'animation' => 'fade-up', 'animationScope' => 'parts']);
        $banner = $this->block('banner', ['heading' => 'Band', 'animation' => 'fade-in', 'animationScope' => 'parts']);
        $banner[0]['id'] = 'banner-9';

        $this->publish([...$hero, ...$banner]);

        $published = (new PageContentStore)->document('home')['published'];

        $this->assertSame('parts', $published[0]['data']['animationScope']);
        $this->assertSame('parts', $published[1]['data']['animationScope']);
    }

    public function test_a_column_persists_its_animation_and_delay(): void
    {
        $section = $this->block('section', ['width' => 'standard']);
        $section[0]['children'] = $this->block('row', ['gap' => 'medium']);
        $section[0]['children'][0]['children'] = $this->block('column', [
            'alignAcross' => 'fill',
            'animation' => 'fade-up',
            'animationDelay' => '200',
        ]);

        $this->publish($section);

        $column = (new PageContentStore)->document('home')['published'][0]['children'][0]['children'][0]['data'];

        $this->assertSame('fade-up', $column['animation']);
        $this->assertSame('200', $column['animationDelay']);
    }
}
