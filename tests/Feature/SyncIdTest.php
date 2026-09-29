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
            'services.syncid.url' => 'https://api.syncid.com.au/leads',
            'services.syncid.key' => 'test-key',
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
            'api.syncid.com.au/*' => Http::response(['id' => 'lead-1'], 201),
        ]);

        $this->sendContact()->assertRedirect();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.syncid.com.au/leads'
                && $request->hasHeader('Authorization', 'Bearer test-key')
                && $request['name'] === 'Janet Reid'
                && $request['email'] === 'janet@example.com'
                && $request['phone'] === '0400 000 000'
                && $request['suburb'] === 'Glen Iris'
                && $request['message'] === 'We are thinking about downsizing next year.'
                && $request['source'] === 'Contact form'
                && $request['page'] === '/landing-page'
                && $request['consented'] === true
                && str_starts_with($request['reference'], 'AF-');
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
            'api.syncid.com.au/*' => Http::response(['error' => 'Unavailable'], 503),
        ]);

        $this->sendContact()->assertRedirect();

        $this->assertSame(1, Enquiry::count());
    }
}
