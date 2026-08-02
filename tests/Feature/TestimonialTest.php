<?php

namespace Tests\Feature;

use App\Content\ContentLibrary;
use App\Models\Media;
use App\Models\Testimonial;
use Tests\TestCase;

class TestimonialTest extends TestCase
{
    private function testimonial(array $overrides = []): Testimonial
    {
        return Testimonial::create(array_merge([
            'name' => 'Janet R.',
            'quote' => 'They took the worry out of selling.',
            'location' => 'Glen Iris',
            'sort_order' => 1,
        ], $overrides));
    }

    private function consented(array $overrides = []): Testimonial
    {
        return $this->testimonial($overrides + [
            'consent_confirmed_at' => now(),
            'consent_confirmed_by' => 'Helen Marsh',
            'active' => true,
        ]);
    }

    public function test_it_saves_every_field_the_scope_asks_for(): void
    {
        $this->post('/cms/testimonials', [
            'name' => 'Brian and Lorraine T.',
            'quote' => 'Honest advice with nothing to sell us.',
            'location' => 'Geelong',
            'headline' => 'Looked after from the first call',
            'image' => '/media/2026/08/brian.jpg',
            'rating' => 5,
        ])->assertRedirect();

        $saved = Testimonial::where('name', 'Brian and Lorraine T.')->sole();

        $this->assertSame('Geelong', $saved->location);
        $this->assertSame('Looked after from the first call', $saved->headline);
        $this->assertSame('/media/2026/08/brian.jpg', $saved->image);
        $this->assertSame(5, $saved->rating);
        $this->assertSame(1, $saved->sort_order);

        /* Nothing arrives published: permission has not been recorded yet. */
        $this->assertFalse($saved->active);
        $this->assertFalse($saved->hasConsent());
    }

    public function test_a_photo_carries_its_description_to_the_reader(): void
    {
        $this->post('/cms/testimonials', [
            'name' => 'Janet R.',
            'quote' => 'They took the worry out of it.',
            'image' => '/media/2026/08/janet.jpg',
            'image_alt' => 'Janet on her front step',
        ])->assertRedirect();

        $saved = Testimonial::sole();

        $this->assertSame('Janet on her front step', $saved->image_alt);
        $this->assertSame('Janet on her front step', $saved->toCard()['avatarAlt']);
    }

    public function test_a_name_and_a_quote_are_required_and_a_rating_is_not(): void
    {
        $this->post('/cms/testimonials', ['name' => '', 'quote' => ''])
            ->assertSessionHasErrors(['name', 'quote']);

        $this->post('/cms/testimonials', ['name' => 'Margaret W.', 'quote' => 'It made the move possible.'])
            ->assertRedirect();

        $this->assertNull(Testimonial::sole()->rating);
    }

    public function test_a_rating_outside_one_to_five_is_rejected(): void
    {
        $this->post('/cms/testimonials', [
            'name' => 'Peter H.', 'quote' => 'Saved us thousands.', 'rating' => 6,
        ])->assertSessionHasErrors('rating');

        $this->assertSame(0, Testimonial::count());
    }

    public function test_markup_is_stripped_from_what_is_saved(): void
    {
        $this->post('/cms/testimonials', [
            'name' => 'Coralie <b>B.</b>',
            'quote' => 'They answered <script>alert(1)</script>every question.',
        ])->assertRedirect();

        $saved = Testimonial::sole();

        $this->assertSame('Coralie B.', $saved->name);
        $this->assertStringNotContainsString('<', $saved->quote);
    }

    public function test_it_cannot_be_published_before_permission_is_recorded(): void
    {
        $t = $this->testimonial();

        $this->patch("/cms/testimonials/{$t->id}", [
            'name' => $t->name, 'quote' => $t->quote, 'active' => true,
        ])->assertSessionHasErrors('active');

        $this->patch("/cms/testimonials/{$t->id}", [
            'name' => $t->name, 'quote' => $t->quote, 'featured' => true,
        ])->assertSessionHasErrors('active');

        $t->refresh();

        $this->assertFalse($t->active);
        $this->assertFalse($t->featured);
    }

    public function test_recording_permission_records_who_and_when(): void
    {
        $t = $this->testimonial();

        $this->post("/cms/testimonials/{$t->id}/consent", ['confirmed' => true])->assertRedirect();

        $t->refresh();

        $this->assertTrue($t->hasConsent());
        $this->assertNotNull($t->consent_confirmed_by);

        $this->patch("/cms/testimonials/{$t->id}", [
            'name' => $t->name, 'quote' => $t->quote, 'active' => true,
        ])->assertSessionHasNoErrors();

        $this->assertTrue($t->refresh()->active);
    }

    public function test_withdrawing_permission_takes_it_off_the_website(): void
    {
        $t = $this->consented(['featured' => true]);

        $this->post("/cms/testimonials/{$t->id}/consent", ['confirmed' => false])->assertRedirect();

        $t->refresh();

        $this->assertFalse($t->hasConsent());
        $this->assertFalse($t->active);
        $this->assertFalse($t->featured);
    }

    public function test_the_library_only_carries_consented_active_testimonials_in_order(): void
    {
        $this->consented(['name' => 'Second', 'sort_order' => 2]);
        $this->consented(['name' => 'First', 'sort_order' => 1]);
        $this->consented(['name' => 'Hidden', 'sort_order' => 3, 'active' => false]);

        /* Active, but permission was never recorded — it must not reach a reader. */
        $this->testimonial(['name' => 'No permission', 'sort_order' => 4, 'active' => true]);

        $names = array_column((new ContentLibrary)->for('home')['testimonials'], 'name');

        $this->assertSame(['First', 'Second'], $names);
    }

    public function test_the_home_page_receives_the_featured_ones(): void
    {
        $this->consented(['name' => 'Featured one', 'featured' => true]);
        $this->consented(['name' => 'Not featured', 'sort_order' => 2]);

        $this->get('/')->assertOk()->assertInertia(function ($page) {
            $props = $page->toArray()['props'];
            $featured = collect($props['library']['testimonials'])->where('featured', true)->pluck('name');

            $this->assertSame(['Featured one'], $featured->values()->all());
            $this->assertContains('testimonials', collect($props['sections'])->pluck('type')->all());
        });
    }

    public function test_toggling_one_flag_leaves_the_other_alone(): void
    {
        $t = $this->consented();

        $this->patch("/cms/testimonials/{$t->id}", ['featured' => true])->assertSessionHasNoErrors();

        $t->refresh();

        /* Sending only the field that changed — the whole record would revert the rest. */
        $this->assertTrue($t->featured);
        $this->assertTrue($t->active);
        $this->assertSame('Janet R.', $t->name);
    }

    public function test_it_reorders(): void
    {
        $a = $this->testimonial(['name' => 'A', 'sort_order' => 1]);
        $b = $this->testimonial(['name' => 'B', 'sort_order' => 2]);

        $this->post('/cms/testimonials/reorder', ['ids' => [$b->id, $a->id]])->assertRedirect();

        $this->assertSame(1, $b->refresh()->sort_order);
        $this->assertSame(2, $a->refresh()->sort_order);
    }

    public function test_reordering_a_filtered_list_leaves_the_hidden_rows_where_they_were(): void
    {
        $first = $this->testimonial(['name' => 'First', 'sort_order' => 1, 'featured' => false]);
        $hidden = $this->testimonial(['name' => 'Hidden by the filter', 'sort_order' => 2, 'featured' => false]);
        $last = $this->testimonial(['name' => 'Last', 'sort_order' => 3, 'featured' => false]);

        /* What the screen sends while a filter is on: two of the three rows. */
        $this->post('/cms/testimonials/reorder', ['ids' => [$last->id, $first->id]])->assertRedirect();

        /* The pair swap the slots they held — 1 and 3 — and the row between them does not move. */
        $this->assertSame(1, $last->refresh()->sort_order);
        $this->assertSame(3, $first->refresh()->sort_order);
        $this->assertSame(2, $hidden->refresh()->sort_order);
    }

    public function test_a_drag_across_several_positions_keeps_the_hidden_rows_still(): void
    {
        $rows = collect(['One', 'Two', 'Hidden', 'Four', 'Five'])
            ->map(fn ($name, $i) => $this->testimonial(['name' => $name, 'sort_order' => $i + 1]));

        /* What a drag sends, unlike the arrows: the visible rows with one moved several places. */
        $this->post('/cms/testimonials/reorder', ['ids' => [
            $rows[1]->id, $rows[3]->id, $rows[4]->id, $rows[0]->id,
        ]])->assertRedirect();

        /* The four visible rows take the four slots they held — 1, 2, 4, 5 — in their new order. */
        $this->assertSame(1, $rows[1]->refresh()->sort_order);
        $this->assertSame(2, $rows[3]->refresh()->sort_order);
        $this->assertSame(4, $rows[4]->refresh()->sort_order);
        $this->assertSame(5, $rows[0]->refresh()->sort_order);
        $this->assertSame(3, $rows[2]->refresh()->sort_order);
    }

    public function test_only_a_super_administrator_can_delete_one(): void
    {
        $t = $this->testimonial();

        $this->actingAs($this->clientAdmin());

        $this->delete("/cms/testimonials/{$t->id}")->assertForbidden();

        $this->assertDatabaseHas('testimonials', ['id' => $t->id]);
    }

    public function test_a_super_administrator_can_delete_one(): void
    {
        $t = $this->testimonial();

        $this->delete("/cms/testimonials/{$t->id}")->assertRedirect();

        $this->assertSame(0, Testimonial::count());
    }

    public function test_the_admin_screen_lists_them_with_their_permission_state(): void
    {
        $this->consented(['name' => 'Has permission']);
        $this->testimonial(['name' => 'Waiting', 'sort_order' => 2]);

        $this->get('/cms/testimonials')->assertOk()->assertInertia(function ($page) {
            $rows = collect($page->toArray()['props']['testimonials']);

            $this->assertTrue($rows->firstWhere('name', 'Has permission')['hasConsent']);
            $this->assertFalse($rows->firstWhere('name', 'Waiting')['hasConsent']);
        });
    }

    public function test_the_builder_can_choose_specific_testimonials(): void
    {
        $t = $this->consented();

        $choices = (new ContentLibrary)->choices()['testimonialChoices'];

        $this->assertSame([['id' => (string) $t->id, 'label' => 'Janet R. — Glen Iris']], $choices);
    }

    public function test_a_photo_in_use_cannot_be_deleted_without_warning(): void
    {
        $medium = Media::create([
            'key' => '2026/08/janet.jpg',
            'name' => 'janet.jpg',
            'mime' => 'image/jpeg',
            'size' => 2048,
            'disk' => 'public',
        ]);

        $this->consented(['image' => $medium->url()]);

        $response = $this->postJson('/cms/media/usage', ['ids' => [$medium->id]]);

        $this->assertSame(['Testimonial: Janet R.'], $response->json('items.0.usedBy'));
    }
}
