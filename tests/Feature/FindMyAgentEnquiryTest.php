<?php

namespace Tests\Feature;

use App\Models\Enquiry;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class FindMyAgentEnquiryTest extends TestCase
{
    private function send(array $overrides = [], array $details = []): TestResponse
    {
        return $this->post('/enquiries', array_replace([
            'source' => Enquiry::FIND_MY_AGENT,
            'name' => 'Jane Wilson',
            'email' => 'jane@example.com',
            'phone' => '0412 345 678',
            'message' => 'Mum’s place needs work before we list it.',
            'consent' => true,
            'page' => '/',
            'details' => array_replace([
                'property_type' => 'house',
                'timeline' => 'within_3_months',
                'best_time' => 'morning',
                'location' => [
                    'place_id' => 'ChIJexample',
                    'suburb' => 'Mosman',
                    'state' => 'NSW',
                    'postcode' => '2088',
                    'description' => 'Mosman NSW 2088',
                    'lat' => -33.8269,
                    'lng' => 151.2437,
                    'free_text' => false,
                ],
            ], $details),
        ], $overrides));
    }

    public function test_a_completed_wizard_is_kept(): void
    {
        $this->send()->assertRedirect()->assertSessionHas('enquiry', fn (array $flash) => $flash['status'] === 'sent'
            && $flash['source'] === Enquiry::FIND_MY_AGENT
            && preg_match('/^AF-\d{4}-\d{5}$/', $flash['reference']) === 1);

        $enquiry = Enquiry::sole();

        $this->assertSame(Enquiry::FIND_MY_AGENT, $enquiry->source);
        $this->assertSame('Jane Wilson', $enquiry->name);
        $this->assertTrue($enquiry->consented);
        $this->assertSame(Enquiry::NEW, $enquiry->status);
        /* Copied out of the answer so the list, the detail header and the search all keep working
           without knowing this form exists. */
        $this->assertSame('Mosman', $enquiry->suburb);
    }

    public function test_the_reference_leads_back_to_the_row(): void
    {
        $this->send();

        $enquiry = Enquiry::sole();

        $this->assertSame(
            sprintf('AF-%s-%05d', $enquiry->created_at->format('Y'), $enquiry->id),
            $enquiry->reference(),
        );
        $this->assertSame($enquiry->reference(), session('enquiry')['reference']);
    }

    public function test_their_own_words_are_kept_and_nothing_is_added_to_them(): void
    {
        $this->send();

        /* The whole reason the answers live in their own column. If the picked answers were composed
           into this string, every wizard enquiry would open with the same boilerplate, the list
           snippet would show it instead of the person, and the search would match the labels. */
        $this->assertSame('Mum’s place needs work before we list it.', Enquiry::sole()->message);
    }

    public function test_the_answers_are_stored_as_keys_not_wording(): void
    {
        $this->send();

        $details = Enquiry::sole()->details;

        $this->assertSame('house', $details['property_type']);
        $this->assertSame('within_3_months', $details['timeline']);
        $this->assertSame('morning', $details['best_time']);
        $this->assertSame('NSW', $details['location']['state']);
        $this->assertSame('2088', $details['location']['postcode']);
        /* Wording is resolved when it is shown, so re-labelling an answer never rewrites a row. */
        $this->assertStringNotContainsString('Now', json_encode($details));
    }

    public function test_the_wording_is_resolved_for_the_screen(): void
    {
        $this->send();

        $this->assertSame([
            ['label' => 'Suburb', 'value' => 'Mosman NSW 2088'],
            ['label' => 'Property type', 'value' => 'House'],
            ['label' => 'Looking to sell', 'value' => 'Now'],
            ['label' => 'Best time to call', 'value' => 'Morning'],
        ], Enquiry::sole()->answers());
    }

    public function test_a_position_is_not_an_answer(): void
    {
        /* The form used to send the index of the chosen card. Accepting that again would make the
           order of a JavaScript array the meaning of every stored answer. */
        $this->send(details: ['property_type' => 0])
            ->assertSessionHasErrors('details.property_type');

        $this->assertSame(0, Enquiry::count());
    }

    public function test_an_answer_that_was_never_offered_is_refused(): void
    {
        $this->send(details: ['property_type' => 'castle'])
            ->assertSessionHasErrors('details.property_type');

        $this->send(details: ['timeline' => 'someday'])
            ->assertSessionHasErrors('details.timeline');

        $this->assertSame(0, Enquiry::count());
    }

    public function test_a_source_this_application_does_not_know_is_refused(): void
    {
        $this->send(['source' => 'partner_portal'])->assertSessionHasErrors('source');

        $this->assertSame(0, Enquiry::count());
    }

    public function test_nothing_is_stored_without_consent(): void
    {
        $this->send(['consent' => false])->assertSessionHasErrors('consent');

        $this->assertSame(0, Enquiry::count());
    }

    public function test_a_phone_number_is_required_here_and_not_on_the_contact_form(): void
    {
        /* The wizard promises a call at a nominated time, so it needs a number to ring. */
        $this->send(['phone' => ''])->assertSessionHasErrors('phone');

        $this->post('/enquiries', [
            'name' => 'Peter Vaughn', 'email' => 'peter@example.com', 'consent' => true,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(1, Enquiry::count());
    }

    public function test_a_suburb_nobody_could_look_up_is_still_accepted(): void
    {
        /* The lookup degrades to free text when Google is unreachable, so a typed suburb has to be
           enough — an outage upstream must not close the form. */
        $this->send(details: ['location' => ['suburb' => 'Little Hampton', 'free_text' => true]])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('Little Hampton', Enquiry::sole()->suburb);
    }

    public function test_only_the_answers_that_were_asked_for_are_kept(): void
    {
        $this->send(details: ['salary' => '90000', 'internal_score' => 7]);

        $details = Enquiry::sole()->details;

        $this->assertArrayNotHasKey('salary', $details);
        $this->assertArrayNotHasKey('internal_score', $details);
    }

    public function test_markup_does_not_survive_the_trip(): void
    {
        $this->send([
            'name' => 'Jane <script>alert(1)</script>Wilson',
            'message' => 'We are <b>ready</b> to sell.',
        ]);

        $enquiry = Enquiry::sole();

        $this->assertStringNotContainsString('<', $enquiry->name);
        $this->assertStringNotContainsString('<', $enquiry->message);
    }

    public function test_it_is_held_to_the_same_rate_limit_as_the_other_form(): void
    {
        /* Both forms post to one route on purpose, so this limit cannot be walked around by using
           the newer of the two. */
        for ($i = 0; $i < 6; $i++) {
            $this->send(['email' => "flood{$i}@example.com"]);
        }

        $this->send(['email' => 'flood-again@example.com'])->assertStatus(429);

        $this->assertSame(6, Enquiry::count());
    }
}
