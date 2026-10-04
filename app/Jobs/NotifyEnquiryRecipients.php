<?php

namespace App\Jobs;

use App\Content\Site;
use App\Logging\Delivery;
use App\Mail\EnquiryReceived;
use App\Models\Enquiry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Emails the addresses listed in Settings → Notifications about one enquiry.
 *
 * Queued so a slow mail server never holds up the visitor, and fail-open like SyncID: the row in the
 * CMS is the record of truth, so a mail failure is logged and nothing else happens.
 */
class NotifyEnquiryRecipients implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $enquiryId) {}

    public static function for(Enquiry $enquiry): void
    {
        if (Site::enquiryRecipients() === []) {
            Delivery::log()->info('Enquiry notification skipped', [
                'enquiry_id' => $enquiry->id,
                'reason' => 'no_recipients',
            ]);

            return;
        }

        self::dispatch($enquiry->id);

        Delivery::log()->info('Enquiry notification queued', [
            'enquiry_id' => $enquiry->id,
            'recipients' => count(Site::enquiryRecipients()),
            'to' => Site::enquiryRecipients(),
            'queue' => config('queue.default'),
        ]);
    }

    public function handle(): void
    {
        $enquiry = Enquiry::find($this->enquiryId);
        $recipients = Site::enquiryRecipients();

        if ($enquiry === null || $recipients === []) {
            Delivery::log()->info('Enquiry notification skipped', [
                'enquiry_id' => $this->enquiryId,
                'reason' => $enquiry === null ? 'enquiry_deleted' : 'no_recipients',
            ]);

            return;
        }

        $mailer = (string) config('mail.default');

        Delivery::log()->info('Enquiry notification sending', array_filter([
            'enquiry_id' => $enquiry->id,
            'source' => $enquiry->source,
            'to' => $recipients,
            'mailer' => $mailer,
            'transport' => self::transport(),
            'from' => config('mail.from.address'),
            'ses_region' => $mailer === 'ses_cross_account' ? config('mail.mailers.ses_cross_account.region') : null,
            'ses_role_arn' => $mailer === 'ses_cross_account' ? config('mail.mailers.ses_cross_account.role_arn') : null,
            'attempt' => $this->attempts(),
            'job_id' => $this->job?->getJobId(),
        ], fn ($value) => $value !== null));

        try {
            $sent = Mail::to($recipients)->send(new EnquiryReceived($enquiry));
        } catch (Throwable $e) {
            Delivery::log()->error('Enquiry notification could not be sent', [
                'enquiry_id' => $enquiry->id,
                'recipients' => count($recipients),
                'to' => $recipients,
                'mailer' => $mailer,
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'cause' => $e->getPrevious()?->getMessage(),
            ]);

            return;
        }

        Delivery::log()->info('Enquiry notification sent', [
            'enquiry_id' => $enquiry->id,
            'recipients' => count($recipients),
            'to' => $recipients,
            'mailer' => $mailer,
            'message_id' => $sent?->getMessageId(),
            'ses_message_id' => $sent?->getOriginalMessage()->getHeaders()->get('X-SES-Message-ID')?->getBodyAsString(),
            'envelope_from' => $sent?->getEnvelope()->getSender()->getAddress(),
            'envelope_to' => $sent ? array_map(fn ($address) => $address->getAddress(), $sent->getEnvelope()->getRecipients()) : [],
        ]);

        if (in_array($mailer, ['log', 'array'], true)) {
            Delivery::log()->warning('Enquiry notification was not delivered: the mailer only writes it to the log', [
                'enquiry_id' => $enquiry->id,
                'mailer' => $mailer,
            ]);
        }
    }

    private static function transport(): ?string
    {
        try {
            return (string) Mail::mailer()->getSymfonyTransport();
        } catch (Throwable $e) {
            return 'unavailable: '.$e->getMessage();
        }
    }
}
