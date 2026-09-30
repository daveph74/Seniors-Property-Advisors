<?php

namespace Tests\Feature;

use App\Content\Site;
use App\Jobs\NotifyEnquiryRecipients;
use App\Mail\EnquiryReceived;
use App\Models\Enquiry;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class EnquiryNotificationTest extends TestCase
{
    private function recipients(array $emails): void
    {
        Site::merge(['notifications' => ['enquiryRecipients' => $emails]]);
    }

    private function contactForm(): void
    {
        $this->post('/enquiries', [
            'name' => 'Janet Reid',
            'email' => 'janet@example.com',
            'phone' => '0400 000 000',
            'message' => 'We are thinking about downsizing next year.',
            'consent' => true,
            'source' => Enquiry::CONTACT_FORM,
            'page' => '/contact',
        ])->assertRedirect();
    }

    private function agentFinder(): void
    {
        $this->post('/enquiries', [
            'source' => Enquiry::FIND_MY_AGENT,
            'name' => 'Jane Wilson',
            'email' => 'jane@example.com',
            'phone' => '0412 345 678',
            'page' => '/',
            'details' => [
                'property_type' => 'house',
                'timeline' => 'within_3_months',
                'best_time' => 'morning',
                'location' => ['street' => '12 Smith Street', 'suburb' => 'Mosman'],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_a_contact_form_enquiry_notifies_the_listed_addresses(): void
    {
        Queue::fake();
        $this->recipients(['advisor@example.com']);

        $this->contactForm();

        Queue::assertPushed(NotifyEnquiryRecipients::class, fn ($job) => $job->enquiryId === Enquiry::sole()->id);
    }

    public function test_an_agent_finder_enquiry_notifies_the_listed_addresses(): void
    {
        Queue::fake();
        $this->recipients(['advisor@example.com']);

        $this->agentFinder();

        Queue::assertPushed(NotifyEnquiryRecipients::class);
    }

    public function test_nothing_is_queued_when_nobody_is_listed(): void
    {
        Queue::fake();

        $this->contactForm();

        Queue::assertNothingPushed();
        $this->assertSame(1, Enquiry::count());
    }

    public function test_every_listed_address_receives_the_email(): void
    {
        Mail::fake();
        $this->recipients(['advisor@example.com', 'office@example.com']);

        $this->contactForm();

        Mail::assertSent(EnquiryReceived::class, fn (EnquiryReceived $mail) => $mail->hasTo('advisor@example.com')
            && $mail->hasTo('office@example.com')
            && $mail->hasReplyTo('janet@example.com'));
    }

    public function test_agent_finder_answers_are_in_the_email_and_contact_form_has_none(): void
    {
        $this->agentFinder();
        $this->contactForm();

        [$wizard, $contact] = Enquiry::orderBy('id')->get()->all();

        $wizardHtml = (new EnquiryReceived($wizard))->render();
        $contactHtml = (new EnquiryReceived($contact))->render();

        $this->assertStringContainsString('New Agent Finder enquiry', $wizardHtml);
        $this->assertStringContainsString('Best time to call', $wizardHtml);
        $this->assertStringContainsString('12 Smith Street', $wizardHtml);
        $this->assertStringContainsString('/cms/enquiries?open='.$wizard->id, $wizardHtml);

        $this->assertStringContainsString('New Contact form enquiry', $contactHtml);
        $this->assertStringNotContainsString('Best time to call', $contactHtml);
        $this->assertStringContainsString('We are thinking about downsizing next year.', $contactHtml);
    }

    public function test_a_mail_failure_leaves_the_enquiry_in_place(): void
    {
        $this->recipients(['advisor@example.com']);
        Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP is down'));

        $this->contactForm();

        $this->assertSame(1, Enquiry::count());
    }
}
