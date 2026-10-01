<?php

namespace Tests\Feature;

use App\Content\Site;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CheckEnquiryDeliveryTest extends TestCase
{
    private function configured(): void
    {
        config([
            'services.syncid.url' => 'https://spa.syncid.com.au/api/website-lead',
            'services.syncid.key' => 'secret-key',
            'services.syncid.office_id' => '1',
            'mail.default' => 'smtp',
        ]);

        Site::merge(['notifications' => ['enquiryRecipients' => ['advisor@example.com']]]);
    }

    public function test_it_posts_the_shape_syncid_documents(): void
    {
        $this->configured();
        Mail::fake();
        Http::fake(['*' => Http::response(['ok' => true], 202)]);

        $this->artisan('enquiries:check-delivery --send')->assertSuccessful();

        Http::assertSent(fn (Request $r) => $r->url() === 'https://spa.syncid.com.au/api/website-lead'
            && $r->hasHeader('X-Api-Key', 'secret-key')
            && $r->hasHeader('Accept', 'application/json')
            && $r->data() === ['office_id' => 1, 'first_name' => 'Test', 'email' => 'test@example.com']);
    }

    public function test_a_refused_key_is_named(): void
    {
        $this->configured();
        Mail::fake();
        Http::fake(['*' => Http::response(['error' => 'Unauthorized'], 401)]);

        $this->artisan('enquiries:check-delivery --send')
            ->expectsOutputToContain('SyncID refused the key')
            ->assertFailed();
    }

    public function test_the_site_root_is_not_the_webhook(): void
    {
        $this->configured();
        config(['services.syncid.url' => 'https://spa.syncid.com.au/']);

        $this->artisan('enquiries:check-delivery')
            ->expectsOutputToContain('/api/website-lead')
            ->assertFailed();
    }

    public function test_a_log_mailer_and_an_empty_list_are_both_reported(): void
    {
        $this->configured();
        config(['mail.default' => 'log']);
        Site::merge(['notifications' => ['enquiryRecipients' => []]]);

        $this->artisan('enquiries:check-delivery')
            ->expectsOutputToContain('No recipients are listed')
            ->expectsOutputToContain('MAIL_MAILER is log')
            ->assertFailed();
    }

    public function test_nothing_is_sent_without_the_send_flag(): void
    {
        $this->configured();
        Http::fake();

        $this->artisan('enquiries:check-delivery')->assertSuccessful();

        Http::assertNothingSent();
    }
}
