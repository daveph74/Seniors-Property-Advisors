<?php

namespace App\Mail;

use App\Models\Enquiry;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The team's alert that an enquiry arrived. Reply-to is the sender, so answering the email answers
 * them; the row in the CMS stays the record of truth, and the link leads straight to it.
 */
class EnquiryReceived extends Mailable
{
    public function __construct(public Enquiry $enquiry) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New {$this->enquiry->sourceLabel()} enquiry — {$this->enquiry->name}",
            replyTo: [new Address($this->enquiry->email, $this->enquiry->name)],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.enquiry-received',
            with: [
                'enquiry' => $this->enquiry,
                'answers' => $this->enquiry->answers(),
                'cmsUrl' => url('/cms/enquiries?open='.$this->enquiry->id),
            ],
        );
    }
}
