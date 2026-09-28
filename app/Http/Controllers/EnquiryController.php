<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEnquiryRequest;
use App\Models\Enquiry;
use Illuminate\Http\RedirectResponse;

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
