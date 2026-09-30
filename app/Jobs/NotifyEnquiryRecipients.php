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

        try {
            Mail::to($recipients)->send(new EnquiryReceived($enquiry));
        } catch (Throwable $e) {
            Delivery::log()->error('Enquiry notification could not be sent', [
                'enquiry_id' => $enquiry->id,
                'recipients' => count($recipients),
                'message' => $e->getMessage(),
            ]);

            return;
        }

        Delivery::log()->info('Enquiry notification sent', [
            'enquiry_id' => $enquiry->id,
            'recipients' => count($recipients),
        ]);
    }
}
