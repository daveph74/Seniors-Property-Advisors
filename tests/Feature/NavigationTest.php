<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Setting;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    private function menus(): array
    {
        $globals = Setting::find('globals')->value;

        return [$globals['nav']['links'], $globals['footer']];
    }

    private function save(array $overrides = []): TestResponse
    {
        return $this->put('/cms/navigation', array_merge([
            'nav' => [
                ['label' => 'How it works', 'href' => '#how'],
                ['label' => 'Resources', 'children' => [['label' => 'FAQs', 'href' => '/faqs']]],
            ],
            'footer' => [
                'columns' => [
                    ['heading' => 'Service', 'links' => [['label' => 'Pricing', 'href' => '/pricing']]],
                ],
                'links' => [['label' => 'Privacy', 'href' => '/privacy-policy']],
            ],
        ], $overrides));
    }

    public function test_a_menu_change_reaches_the_website(): void
    {
        $this->save()->assertRedirect()->assertSessionHasNoErrors();

        /* The whole point: the screen used to accept this and throw it away. */
        $this->get('/')->assertOk()->assertInertia(function ($page) {
            $nav = $page->toArray()['props']['globals']['nav']['links'];

            $this->assertSame(['How it works', 'Resources'], array_column($nav, 'label'));
            $this->assertSame('/faqs', $nav[1]['children'][0]['href']);
        });
    }

    public function test_an_item_with_children_leads_nowhere_itself(): void
    {
        $this->save(['nav' => [
            ['label' => 'Resources', 'href' => '/somewhere', 'children' => [
                ['label' => 'Blog', 'href' => '/blog'],
            ]],
        ]])->assertRedirect();

        [$nav] = $this->menus();

        /* A parent is a trigger, not a link. Keeping the typed address would send people to a page
           that may not exist while its dropdown sat unopened. */
        $this->assertNull($nav[0]['href']);
        $this->assertCount(1, $nav[0]['children']);
    }

    public function test_the_order_is_kept_exactly_as_given(): void
    {
        $this->save(['nav' => [
            ['label' => 'Third', 'href' => '/c'],
            ['label' => 'First', 'href' => '/a'],
            ['label' => 'Second', 'href' => '/b'],
        ]])->assertRedirect();

        [$nav] = $this->menus();

        $this->assertSame(['Third', 'First', 'Second'], array_column($nav, 'label'));
    }

    public function test_every_item_needs_wording(): void
    {
        $before = $this->menus();

        $this->save(['nav' => [['label' => '', 'href' => '/somewhere']]])
            ->assertSessionHasErrors('nav.0.label');

        $this->assertSame($before, $this->menus());
    }

    public function test_markup_alone_is_not_wording(): void
    {
        $this->save(['nav' => [['label' => '<hr>', 'href' => '/somewhere']]])
            ->assertSessionHasErrors('nav.0.label');
    }

    public function test_an_item_inside_a_menu_has_to_lead_somewhere(): void
    {
        $this->save(['nav' => [
            ['label' => 'Resources', 'children' => [['label' => 'Blog', 'href' => '']]],
        ]])->assertSessionHasErrors('nav.0.children.0.href');
    }

    public function test_a_footer_column_needs_a_heading(): void
    {
        $this->save(['footer' => [
            'columns' => [['heading' => '', 'links' => []]],
            'links' => [],
        ]])->assertSessionHasErrors('footer.columns.0.heading');
    }

    public function test_a_link_with_no_address_is_stored_without_one(): void
    {
        $this->save(['footer' => [
            'columns' => [['heading' => 'Service', 'links' => [['label' => 'Coming soon', 'href' => '']]]],
            'links' => [],
        ]])->assertRedirect();

        [, $footer] = $this->menus();

        /* The footer renders a link with no address as plain text, which is how "Mon–Fri, 8am–6pm"
           already sits in the contact column. */
        $this->assertArrayNotHasKey('href', $footer['columns'][0]['links'][0]);
    }

    public function test_the_screen_offers_published_pages_as_targets(): void
    {
        Page::create([
            'cms_id' => Page::max('cms_id') + 1,
            'slug' => 'services', 'url' => '/services', 'title' => 'Our services', 'status' => 'published',
        ]);
        Page::create([
            'cms_id' => Page::max('cms_id') + 1,
            'slug' => 'hidden', 'url' => '/hidden', 'title' => 'A draft', 'status' => 'draft',
        ]);

        $this->get('/cms/navigation')->assertOk()->assertInertia(function ($page) {
            $targets = array_column($page->toArray()['props']['pages'], 'href');

            $this->assertContains('/services', $targets);
            /* Offering a draft would put a menu item in front of a 404. */
            $this->assertNotContains('/hidden', $targets);
        });
    }

    public function test_a_guest_cannot_read_or_change_the_menus(): void
    {
        auth()->logout();

        $this->get('/cms/navigation')->assertRedirect('/login');
        $this->put('/cms/navigation', [])->assertRedirect('/login');
    }
}
