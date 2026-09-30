<?php

namespace Tests\Feature;

use App\Models\Enquiry;
use Illuminate\Support\Facades\Http;
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

    public function test_a_contact_form_enquiry_is_forwarded_to_syncid(): void
    {
        Http::fake([
            'spa.syncid.com.au/*' => Http::response(['message' => 'Lead accepted for processing'], 202),
        ]);

        $this->sendContact()->assertRedirect();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://spa.syncid.com.au/api/website-lead'
                && $request->hasHeader('X-Api-Key', 'test-key')
                && $request['office_id'] === 1
                && $request['first_name'] === 'Janet'
                && $request['last_name'] === 'Reid'
                && $request['email'] === 'janet@example.com'
                && $request['phone'] === '0400 000 000'
                && $request['suburb'] === 'Glen Iris'
                && $request['message'] === 'We are thinking about downsizing next year.'
                && $request['source'] === 'Contact form'
                && $request['campaign'] === 'landing-page'
                && str_starts_with($request['external_id'], 'AF-');
        });
    }

    public function test_agent_finder_enquiries_are_not_forwarded(): void
    {
        Http::fake();

        $this->post('/enquiries', [
            'name' => 'Janet Reid',
            'email' => 'janet@example.com',
            'phone' => '0400 000 000',
            'consent' => true,
            'source' => Enquiry::FIND_MY_AGENT,
            'page' => '/',
            'details' => [
                'property_type' => 'house',
                'timeline' => 'three_to_six_months',
                'best_time' => 'morning',
                'location' => ['street' => '12 Smith Street'],
            ],
        ])->assertRedirect();

        Http::assertNothingSent();
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
