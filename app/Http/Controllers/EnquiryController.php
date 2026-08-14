<?php

namespace App\Http\Controllers;

use App\Events\EnquiryReceived;
use App\Http\Requests\StoreEnquiryRequest;
use App\Models\Enquiry;
use Illuminate\Http\RedirectResponse;
use Throwable;

/**
 * Takes an enquiry and keeps it.
 *
 * Both public forms arrive here — the contact form and Agent Finder — and what they may
 * send is `StoreEnquiryRequest`'s business, per scope §12. This is the whole of what happens after.
 */
class EnquiryController extends Controller
{
    public function store(StoreEnquiryRequest $request): RedirectResponse
    {
        $enquiry = Enquiry::create($request->toEnquiry());

        /*
         * Tells any open CMS screen that the inbox has changed, and nothing more than that — the
         * screen then asks for the data itself.
         *
         * The event is queued, so a socket server that is down becomes a failed job rather than a
         * failed enquiry. The try is for the case that queuing itself is synchronous, as it is in the
         * test environment: on a `sync` driver the broadcast happens inside this request, and without
         * this an unreachable Reverb would answer a visitor's enquiry with a 500 after having already
         * saved it. Their enquiry is kept either way; the notification is the part allowed to fail.
         */
        try {
            EnquiryReceived::dispatch();
        } catch (Throwable $e) {
            report($e);
        }

        /*
         * The confirmation wording is the editor's, so the page shows that — this only says something
         * arrived, and hands back the reference the wizard shows the sender.
         *
         * One key holding both facts rather than two that could disagree: `status` is what the contact
         * form has always read, `reference` is what the wizard needs.
         */
        return back()->with('enquiry', [
            'status' => 'sent',
            'source' => $enquiry->source,
            'reference' => $enquiry->reference(),
        ]);
    }
}
