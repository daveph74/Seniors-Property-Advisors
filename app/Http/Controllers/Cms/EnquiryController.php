<?php

namespace App\Http\Controllers\Cms;

use App\Cms\Like;
use App\Cms\Listing;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Enquiry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
    /** How much of the message a list row carries. The rest is a click away in the modal. */
    private const SNIPPET = 160;

    /** @var array<int, string> the states of the work, as the filter offers them */
    private const SHOW = ['new', 'handled', 'all'];

    public function index(Request $request): Response
    {
        /*
         * Both filters are normalised to something real and handed back as such. A value the screen
         * does not offer used to fall through to "everything" while the control showed nothing
         * selected — harmless-looking until a tab strip made it visible as a row of unlit buttons,
         * which reads as a broken screen rather than a mistyped address.
         */
        $show = $this->oneOf($request->query('show'), self::SHOW, 'new');
        $source = $this->oneOf($request->query('source'), array_keys(Enquiry::SOURCES), 'all');
        $term = trim((string) $request->query('q', ''));

        $open = $request->integer('open') ?: null;

        /* One narrowed starting point for the list and both counts, so a number on this screen
           cannot describe a different set of rows than the list under it. */
        $scoped = Enquiry::query()->fromSource($source);

        $query = (clone $scoped)
            /* "Waiting for a reply" means anything not finished, so something picked up but not
               closed stays in the default view rather than dropping out of sight. */
            ->when($show === 'new', fn ($q) => $q->outstanding())
            ->when($show === 'handled', fn ($q) => $q->where('status', Enquiry::DEALT_WITH))
            /* Searched here, and across every enquiry rather than the page on screen — the box
               used to filter the loaded rows, which with paging would quietly search a
               twenty-fifth of the inbox. The message is included: the global palette leaves it out
               so nobody browses an index of people's circumstances, but this is the screen whose
               job is reading them. */
            ->when($term !== '', fn ($q) => Like::any($q, $term, ['name', 'email', 'suburb', 'message']))
            /* The index is on created_at and several can share a second, so id is the tiebreak
               that makes "newest first" mean the same thing twice running. */
            ->latest('created_at')
            ->latest('id');

        ['rows' => $enquiries, 'meta' => $meta] = Listing::slice($query, $request);

        return Inertia::render('Cms/Enquiries/Index', [
            'enquiries' => $enquiries->map(fn (Enquiry $enquiry) => [
                'id' => $enquiry->id,
                'name' => $enquiry->name,
                'at' => $enquiry->created_at?->toIso8601String(),
                'status' => $enquiry->status,
                'statusLabel' => $enquiry->statusLabel(),
                'readAt' => $enquiry->read_at?->toIso8601String(),
                /* A taste of it only. At a hundred a page the message bodies were most of what
                   went down the wire, to be shown as one clipped line. */
                'snippet' => $this->snippet($enquiry),
            ])->all(),
            /* Loaded by id, not found among the rows above. The bell links straight to an enquiry
               and cannot know which filter or page it would land on — searching the loaded list
               meant a link to a dealt-with one, or to anything past page one, opened nothing. */
            'opened' => $this->detail($open),
            'filters' => ['show' => $show, 'source' => $source, 'q' => $term],
            'statuses' => Enquiry::STATUSES,
            'sources' => Enquiry::SOURCES,
            'pagination' => $meta,
            /*
             * Two numbers, and they answer for whichever form is being looked at — the counts sit on
             * the status filter, and that filter lives inside the source tab. Left whole they would
             * say "waiting for a reply (12)" above three rows.
             *
             * Search is deliberately not applied: the pager already says how many a search found, and
             * a count that moved on every keystroke would cost more than it told anybody.
             */
            'counts' => [
                'new' => (clone $scoped)->outstanding()->count(),
                'all' => (clone $scoped)->count(),
            ],
        ]);
    }

    /** The rest of the screen assumes a filter is one of its own options. This is what makes that so. */
    private function oneOf(mixed $value, array $allowed, string $fallback): string
    {
        return in_array($value, $allowed, true) ? (string) $value : $fallback;
    }

    /**
     * A row's one line of context.
     *
     * The wizard's notes are optional, so a row whose sender did not add any would otherwise be a name
     * and a time on an empty line, sitting among rows that all have something — which reads as a
     * damaged record rather than a short enquiry. What they picked stands in.
     */
    private function snippet(Enquiry $enquiry): string
    {
        if (filled($enquiry->message)) {
            return Str::limit((string) $enquiry->message, self::SNIPPET);
        }

        return implode(' · ', array_column($enquiry->answers(), 'value'));
    }

    private function detail(?int $id): ?array
    {
        $enquiry = $id === null ? null : Enquiry::find($id);

        return $enquiry === null ? null : [
            'id' => $enquiry->id,
            'name' => $enquiry->name,
            'email' => $enquiry->email,
            'phone' => $enquiry->phone,
            'suburb' => $enquiry->suburb,
            'message' => $enquiry->message,
            'consented' => $enquiry->consented,
            'page' => $enquiry->page_slug,
            'sourceLabel' => $enquiry->sourceLabel(),
            /* What the sender is quoting if they ring before anybody has called them. */
            'reference' => $enquiry->reference(),
            /* Already wording, already in reading order, and empty for a contact-form enquiry so the
               screen renders nothing rather than a heading over four dashes. */
            'answers' => $enquiry->answers(),
            'at' => $enquiry->created_at?->toIso8601String(),
            'status' => $enquiry->status,
            'statusLabel' => $enquiry->statusLabel(),
            'statusChangedAt' => $enquiry->status_changed_at?->toIso8601String(),
            'readAt' => $enquiry->read_at?->toIso8601String(),
        ];
    }

    /**
     * Reading one is a change, so it is a POST.
     *
     * This used to happen inside `index()` whenever `?open=` was present, which made a GET write to
     * the database — and a GET is the one method anything feels free to make on your behalf. A link
     * prefetched by a browser or an extension would have marked an enquiry read that nobody opened.
     * Only the badge was ever at stake, but a safe method should stay safe.
     */
    public function read(Enquiry $enquiry): RedirectResponse
    {
        /* Guarded so opening the same enquiry twice does not move when it was first read. */
        if ($enquiry->read_at === null) {
            $enquiry->update(['read_at' => now()]);
        }

        return back();
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
