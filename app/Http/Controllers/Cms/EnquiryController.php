<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Enquiry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * What people send through the contact form.
 *
 * The form has worked since it was built, but nothing ever showed what it collected — enquiries
 * went into a table only a database client could read, and its one state column sat unused. A form
 * nobody reads is worse than no form: it invites somebody to ask for help and then loses the
 * request.
 *
 * Read and mark, never edit. The name, email, phone, suburb and message are the enquirer's own
 * words, and this screen has no business changing them — which is why setting the status is its own
 * single-key route rather than a general `update()` that would be an editable-enquiry endpoint by
 * construction.
 */
class EnquiryController extends Controller
{
    public const PER_PAGE = 100;

    public function index(Request $request): Response
    {
        $show = (string) $request->query('show', 'new');

        /* Opening one is what marks it read, and the header's counter is a closure resolved after
           this returns — so the badge falls in the same response that opens the enquiry, with no
           second request and no reload. A write on a GET, which is what "read on view" means
           everywhere; it is confined to an enquiry that exists and is about to be shown. */
        $open = $request->integer('open') ?: null;

        if ($open !== null) {
            Enquiry::whereKey($open)->unread()->update(['read_at' => now()]);
        }

        $enquiries = Enquiry::query()
            /* "Waiting for a reply" means anything not finished, so something picked up but not
               closed stays in the default view rather than dropping out of sight. */
            ->when($show === 'new', fn ($query) => $query->outstanding())
            ->when($show === 'handled', fn ($query) => $query->where('status', Enquiry::DEALT_WITH))
            /* The index is on created_at and several can share a second, so id is the tiebreak
               that makes "newest first" mean the same thing twice running. */
            ->latest('created_at')
            ->latest('id')
            ->limit(self::PER_PAGE)
            ->get();

        return Inertia::render('Cms/Enquiries/Index', [
            'enquiries' => $enquiries->map(fn (Enquiry $enquiry) => [
                'id' => $enquiry->id,
                'name' => $enquiry->name,
                'email' => $enquiry->email,
                'phone' => $enquiry->phone,
                'suburb' => $enquiry->suburb,
                'message' => $enquiry->message,
                'consented' => $enquiry->consented,
                'page' => $enquiry->page_slug,
                'at' => $enquiry->created_at?->toIso8601String(),
                'status' => $enquiry->status,
                'statusLabel' => $enquiry->statusLabel(),
                'statusChangedAt' => $enquiry->status_changed_at?->toIso8601String(),
                'readAt' => $enquiry->read_at?->toIso8601String(),
            ])->all(),
            'filters' => ['show' => $show],
            'statuses' => Enquiry::STATUSES,
            'open' => $open,
            'counts' => [
                'new' => Enquiry::outstanding()->count(),
                'all' => Enquiry::count(),
            ],
            'perPage' => self::PER_PAGE,
        ]);
    }

    /**
     * The one thing this screen may change. Still its own single-key route rather than an
     * `update()`, for the reason above — a general endpoint here would be an editable-enquiry
     * endpoint by construction, whatever the request happened to carry.
     */
    public function status(Request $request, Enquiry $enquiry): RedirectResponse
    {
        $status = $request->validate([
            'status' => ['required', Rule::in(array_keys(Enquiry::STATUSES))],
        ])['status'];

        $enquiry->update(['status' => $status, 'status_changed_at' => now()]);

        return back();
    }

    /**
     * A real delete, and deliberately not part of "Recently deleted".
     *
     * The only honest reason to delete an enquiry is somebody asking to be forgotten. A bin that
     * keeps a recoverable copy of the details being erased, in a screen labelled as recoverable,
     * would defeat the request — and an enquiry is not content anybody here authored.
     *
     * `note`, not `record`: `Activity::labelFor()` falls through to the `name` field, so recording
     * this the usual way would copy the enquirer's name into the audit log, a table with no delete
     * path of its own. Erasing a person while permanently minting a copy of their name is not
     * erasing them.
     */
    public function destroy(Enquiry $enquiry): RedirectResponse
    {
        Activity::note('deleted', 'Enquiry', 'Enquiry #'.$enquiry->id);

        $enquiry->delete();

        return back();
    }
}
