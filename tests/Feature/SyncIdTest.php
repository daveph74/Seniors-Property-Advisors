<?php

namespace Tests\Feature;

use App\Models\Enquiry;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class SyncIdTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.syncid.url' => 'https://spa.syncid.com.au/api/website-lead',
            'services.syncid.key' => 'test-key',
            'services.syncid.office_id' => 1,
        ]);
    }

    private function sendContact(array $overrides = [])
    {
        return $this->post('/enquiries', array_merge([
            'name' => 'Janet Reid',
            'email' => 'janet@example.com',
            'phone' => '0400 000 000',
            'suburb' => 'Glen Iris',
            'message' => 'We are thinking about downsizing next year.',
            'consent' => true,
            'source' => Enquiry::CONTACT_FORM,
            'page' => '/landing-page',
        ], $overrides));
    }

    private function sendWizard(array $overrides = [])
    {
        return $this->post('/enquiries', array_replace_recursive([
            'name' => 'Margaret Smith',
            'email' => 'margaret@example.com',
            'phone' => '0412 345 678',
            'message' => 'Mum is moving into care, we need to sell the family home.',
            'source' => Enquiry::FIND_MY_AGENT,
            'page' => '/how-it-works',
            'details' => [
                'property_type' => 'house',
                'timeline' => 'in_3_6_months',
                'best_time' => 'morning',
                'location' => [
                    'street' => '12 Smith Street',
                    'suburb' => 'Mosman',
                    'state' => 'NSW',
                    'postcode' => '2088',
                    'place_id' => 'ChIJabc123',
                    'lat' => -33.829,
                    'lng' => 151.244,
                ],
            ],
        ], $overrides));
    }

    private function acceptEverything(): void
    {
        Http::fake([
            'spa.syncid.com.au/*' => Http::response(['message' => 'Lead accepted for processing'], 202),
        ]);
    }

    private function sentBody(): array
    {
        $recorded = Http::recorded();

        $this->assertCount(1, $recorded);

        return $recorded[0][0]->data();
    }

    public function test_a_contact_form_enquiry_is_forwarded_to_syncid(): void
    {
        $this->acceptEverything();

        $this->sendContact()->assertRedirect();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://spa.syncid.com.au/api/website-lead'
                && $request->hasHeader('X-Api-Key', 'test-key')
                && $request->hasHeader('Content-Type', 'application/json');
        });

        $body = $this->sentBody();
        ksort($body);

        $this->assertSame([
            'campaign' => 'landing-page',
            'email' => 'janet@example.com',
            'external_id' => Enquiry::sole()->reference(),
            'first_name' => 'Janet',
            'last_name' => 'Reid',
            'message' => 'We are thinking about downsizing next year.',
            'mobile' => '0400 000 000',
            'office_id' => 1,
            'source' => 'contact-form',
            'suburb' => 'Glen Iris',
        ], $body);
    }

    public function test_an_agent_finder_enquiry_is_sent_in_the_shape_syncid_reads(): void
    {
        $this->acceptEverything();

        $this->sendWizard()->assertRedirect();

        $expected = [
            'office_id' => 1,
            'first_name' => 'Margaret',
            'last_name' => 'Smith',
            'email' => 'margaret@example.com',
            'mobile' => '0412 345 678',
            'suburb' => 'Mosman',
            'state' => 'NSW',
            'postcode' => '2088',
            'property_address' => '12 Smith Street',
            'property_suburb' => 'Mosman',
            'property_state' => 'NSW',
            'property_postcode' => '2088',
            'message' => "Mum is moving into care, we need to sell the family home.\n\nProperty type: House\nTimeline: In 3 – 6 months\nBest time to call: Morning",
            'source' => 'agent-finder',
            'campaign' => 'how-it-works',
            'external_id' => Enquiry::sole()->reference(),
        ];

        $body = $this->sentBody();
        ksort($expected);
        ksort($body);

        $this->assertSame($expected, $body);
    }

    public function test_a_unit_stays_on_the_property_address(): void
    {
        $this->acceptEverything();

        $this->sendWizard(['details' => ['location' => ['street' => '3/12 Smith Street']]])->assertRedirect();

        $this->assertSame('3/12 Smith Street', $this->sentBody()['property_address']);
    }

    public function test_without_notes_the_message_is_the_answers_alone(): void
    {
        $this->acceptEverything();

        $this->sendWizard(['message' => ''])->assertRedirect();

        $this->assertSame(
            "Property type: House\nTimeline: In 3 – 6 months\nBest time to call: Morning",
            $this->sentBody()['message'],
        );
    }

    public function test_a_landline_is_sent_as_phone_and_a_mobile_as_mobile(): void
    {
        foreach ([
            '02 9876 5432' => 'phone',
            '(02) 9876-5432' => 'phone',
            '+61 412 345 678' => 'mobile',
            '0412.345.678' => 'mobile',
        ] as $number => $field) {
            Http::fake([
                'spa.syncid.com.au/*' => Http::response([], 202),
            ]);

            $this->sendContact(['phone' => $number])->assertRedirect();

            $body = Http::recorded()->last()[0]->data();

            $this->assertSame($number, $body[$field] ?? null, $number);
            $this->assertArrayNotHasKey($field === 'phone' ? 'mobile' : 'phone', $body, $number);
        }
    }

    public function test_values_are_cut_to_what_syncid_accepts(): void
    {
        $this->acceptEverything();

        $this->sendWizard([
            'page' => '/'.str_repeat('a', 150),
            'details' => ['location' => ['state' => 'New South Wales', 'postcode' => '20881234567']],
        ])->assertRedirect();

        $body = $this->sentBody();

        $this->assertSame(100, mb_strlen($body['campaign']));
        $this->assertSame('New South ', $body['state']);
        $this->assertSame('New South ', $body['property_state']);
        $this->assertSame('2088123456', $body['postcode']);
    }

    public function test_a_server_error_is_retried_until_the_lead_is_accepted(): void
    {
        Sleep::fake();

        Http::fakeSequence('spa.syncid.com.au/*')
            ->push(['message' => 'Service unavailable'], 503)
            ->push(['message' => 'Lead accepted for processing'], 202);

        $this->sendContact()->assertRedirect();

        Http::assertSentCount(2);
    }

    public function test_a_network_error_is_retried(): void
    {
        Sleep::fake();

        Http::fakeSequence('spa.syncid.com.au/*')
            ->pushFailedConnection()
            ->push(['message' => 'Lead accepted for processing'], 202);

        $this->sendContact()->assertRedirect();

        Http::assertSentCount(2);
    }

    public function test_an_accepted_or_refused_lead_is_never_resent(): void
    {
        Sleep::fake();

        foreach ([202, 401, 422] as $status) {
            Http::fake([
                'spa.syncid.com.au/*' => Http::response(['errors' => ['email' => ['Invalid.']]], $status),
            ]);

            $this->sendContact()->assertRedirect();

            Http::assertSentCount(1);
        }
    }

    public function test_retries_stop_after_three_attempts(): void
    {
        Sleep::fake();

        Http::fake([
            'spa.syncid.com.au/*' => Http::response(['message' => 'Bad gateway'], 502),
        ]);

        $this->sendContact()->assertRedirect();

        Http::assertSentCount(3);
        $this->assertSame(1, Enquiry::count());
    }

    public function test_nothing_is_sent_when_syncid_is_not_configured(): void
    {
        config(['services.syncid.url' => null]);

        Http::fake();

        $this->sendContact()->assertRedirect();

        Http::assertNothingSent();
    }

    public function test_a_syncid_failure_does_not_stop_the_enquiry_being_kept(): void
    {
        Http::fake([
            'spa.syncid.com.au/*' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        $this->sendContact()->assertRedirect();

        $this->assertSame(1, Enquiry::count());
    }
}
