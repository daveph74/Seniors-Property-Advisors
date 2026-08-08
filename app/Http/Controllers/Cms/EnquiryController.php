<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Enquiry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * What people send through the contact form.
 *
 * The form has worked since it was built, but nothing ever showed what it collected — enquiries
 * went into a table only a database client could read, and `handled_at` sat unused. A form nobody
 * reads is worse than no form: it invites somebody to ask for help and then loses the request.
 *
 * Read and mark, never edit. The name, email, phone, suburb and message are the enquirer's own
 * words, and this screen has no business changing them — which is why marking one dealt with is
 * its own single-key route rather than a general `update()` that would be an editable-enquiry
 * endpoint by construction.
 */
class EnquiryController extends Controller
{
    public const PER_PAGE = 100;

    public function index(Request $request): Response
    {
        $show = (string) $request->query('show', 'new');

        $enquiries = Enquiry::query()
            ->when($show === 'new', fn ($query) => $query->whereNull('handled_at'))
            ->when($show === 'handled', fn ($query) => $query->whereNotNull('handled_at'))
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
                'handledAt' => $enquiry->handled_at?->toIso8601String(),
            ])->all(),
            'filters' => ['show' => $show],
            'counts' => [
                'new' => Enquiry::whereNull('handled_at')->count(),
                'all' => Enquiry::count(),
            ],
            'perPage' => self::PER_PAGE,
        ]);
    }

    public function handled(Request $request, Enquiry $enquiry): RedirectResponse
    {
        $enquiry->update(['handled_at' => $request->boolean('handled') ? now() : null]);

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
