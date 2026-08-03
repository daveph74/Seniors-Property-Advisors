<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Setting;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class GlobalContentTest extends TestCase
{
    private function globals(): array
    {
        return Setting::find('globals')->value;
    }

    private function save(array $overrides = []): TestResponse
    {
        return $this->put('/cms/global-content', array_merge([
            'notice' => ['active' => true, 'text' => 'A free guide', 'href' => '/guide'],
            'logo' => ['alt' => 'Seniors Property Advisors'],
            'phone' => ['label' => '1300 000 111'],
            'cta' => ['label' => 'Find My Agent'],
            'footer' => [
                'word' => 'Agent Finder',
                'blurb' => 'An independent service.',
                'address' => "Level 14, 50 Carrington St,\nSydney NSW 2000",
                'legal' => '© 2026 Seniors Property Advisors',
            ],
        ], $overrides));
    }

    public function test_a_change_reaches_every_page(): void
    {
        $this->save()->assertRedirect()->assertSessionHasNoErrors();

        $this->get('/')->assertOk()->assertInertia(function ($page) {
            $globals = $page->toArray()['props']['globals'];

            $this->assertSame('A free guide', $globals['notice']['text']);
            $this->assertSame('Agent Finder', $globals['footer']['word']);
            $this->assertSame('Find My Agent', $globals['nav']['cta']['label']);
        });
    }

    public function test_the_dialling_link_follows_the_number(): void
    {
        $this->save(['phone' => ['label' => '1300 277 999']])->assertRedirect();

        /* Typed separately, the two drift: the header reads correctly and dials somebody else. */
        $this->assertSame('tel:1300277999', $this->globals()['phone']['href']);
    }

    public function test_the_announcement_can_be_hidden_without_losing_its_wording(): void
    {
        $this->save(['notice' => ['active' => false, 'text' => 'Back in April', 'href' => '']])
            ->assertRedirect()->assertSessionHasNoErrors();

        $notice = $this->globals()['notice'];

        $this->assertFalse($notice['active']);
        $this->assertSame('Back in April', $notice['text']);
    }

    public function test_a_hidden_announcement_needs_no_wording(): void
    {
        $this->save(['notice' => ['active' => false, 'text' => '', 'href' => '']])
            ->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_a_showing_announcement_does(): void
    {
        $this->save(['notice' => ['active' => true, 'text' => '', 'href' => '']])
            ->assertSessionHasErrors('notice.text');
    }

    public function test_markup_alone_is_not_wording(): void
    {
        /* Validating before stripping is how "<hr>" once passed `required` and stored nothing. */
        $this->save(['footer' => [
            'word' => '<hr>',
            'blurb' => 'An independent service.',
            'address' => '',
            'legal' => '© 2026',
        ]])->assertSessionHasErrors('footer.word');
    }

    public function test_the_address_is_stored_as_lines_and_blank_ones_dropped(): void
    {
        $this->save(['footer' => [
            'word' => 'Agent Finder',
            'blurb' => 'An independent service.',
            'address' => "Level 14,\n\n  Sydney NSW 2000  \n",
            'legal' => '© 2026',
        ]])->assertRedirect();

        $this->assertSame(
            ['Level 14,', 'Sydney NSW 2000'],
            $this->globals()['footer']['address'],
        );
    }

    public function test_saving_leaves_the_menus_alone(): void
    {
        $before = $this->globals();

        $this->save()->assertRedirect();

        $after = $this->globals();

        /* Both screens write the same row. Replacing it rather than merging would have this screen
           delete every menu in the website. */
        $this->assertSame($before['nav']['links'], $after['nav']['links']);
        $this->assertSame($before['footer']['columns'], $after['footer']['columns']);
        $this->assertSame($before['footer']['links'], $after['footer']['links']);
    }

    public function test_the_button_keeps_doing_what_it_did(): void
    {
        $before = $this->globals()['nav']['cta'];

        $this->save(['cta' => ['label' => 'Start now']])->assertRedirect();

        $after = $this->globals()['nav']['cta'];

        /* Only the wording is content. What the button opens is how the website works. */
        $this->assertSame('Start now', $after['label']);
        $this->assertSame($before['action'], $after['action']);
    }

    public function test_the_screen_loads_the_real_values(): void
    {
        $this->get('/cms/global-content')->assertOk()->assertInertia(function ($page) {
            $globals = $page->toArray()['props']['globals'];

            $this->assertNotSame('', $globals['footer']['legal']);
            $this->assertStringContainsString("\n", $globals['footer']['address']);
            $this->assertTrue($globals['notice']['active']);
        });
    }

    public function test_the_logo_image_cannot_be_changed_through_this_screen(): void
    {
        $was = $this->globals()['logo']['src'];

        $this->save(['logo' => ['src' => '/media/2026/08/anything.png', 'alt' => 'Still described']])
            ->assertRedirect()->assertSessionHasNoErrors();

        $logo = $this->globals()['logo'];

        /* The lockup is an inline <svg>, so an address never chose the artwork — it only switched
           `BrandLogo` to an <img>, and a wide one blows out the header. The description is content;
           the drawing is design. */
        $this->assertSame($was, $logo['src']);
        $this->assertSame('Still described', $logo['alt']);
    }

    public function test_the_logo_still_needs_a_description(): void
    {
        $this->save(['logo' => ['alt' => '']])->assertSessionHasErrors('logo.alt');
    }

    public function test_a_change_is_recorded_against_the_part_that_moved(): void
    {
        $this->save(['footer' => [
            'word' => 'Agent Finder',
            'blurb' => 'Reworded.',
            'address' => '',
            'legal' => '© 2026',
        ]])->assertRedirect();

        $entry = Activity::where('subject_type', 'GlobalContent')->latest('id')->first();

        /* Without this a person could reword every page in the website and leave no trace, while a
           typo fixed in one article shows up twice. */
        $this->assertNotNull($entry);
        $this->assertSame('edited', $entry->action);
        $this->assertStringContainsString('Footer', $entry->subject_label);
    }

    public function test_saving_without_changing_anything_is_not_an_event(): void
    {
        $this->save()->assertRedirect();
        $count = Activity::where('subject_type', 'GlobalContent')->count();

        $this->save()->assertRedirect();

        $this->assertSame($count, Activity::where('subject_type', 'GlobalContent')->count());
    }

    public function test_a_guest_cannot_read_or_change_it(): void
    {
        auth()->logout();

        $this->get('/cms/global-content')->assertRedirect('/login');
        $this->put('/cms/global-content', [])->assertRedirect('/login');
    }
}
