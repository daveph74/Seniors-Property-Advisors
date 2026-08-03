<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\BlogPost;
use App\Models\Enquiry;
use App\Models\Faq;
use App\Models\Media;
use App\Models\Page;
use App\Models\Testimonial;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Scope §3. Everything here is counted or read at the moment the page loads; the screen used to
 * render invented figures, which is worse than an empty dashboard because it reads as fact.
 */
class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Cms/Dashboard', [
            'greeting' => [
                /* The whole name. Taking the first word gave "Hello, Site" for an account called
                   Site Administrator, which is nobody's name. */
                'name' => $request->user()->name,
                'summary' => $this->summary(),
            ],
            'counts' => $this->counts(),
            'recentlyEdited' => $this->recentlyEdited(),
            'awaitingPublication' => $this->awaitingPublication(),
            'recentlyPublished' => $this->recentlyPublished(),
            'activity' => $this->activity(),
            'lastPublishedAt' => Page::whereNotNull('published_at')->max('published_at'),
        ]);
    }

    /** The six §3 suggests, plus the two that answer "is anything waiting for me". */
    private function counts(): array
    {
        return [
            ['label' => 'Published pages', 'n' => Page::where('status', 'published')->count()],
            ['label' => 'Draft pages', 'n' => Page::where('status', 'draft')->count()],
            ['label' => 'Published articles', 'n' => BlogPost::where('status', 'published')->count()],
            ['label' => 'Draft articles', 'n' => BlogPost::where('status', 'draft')->count()],
            ['label' => 'Active FAQs', 'n' => Faq::where('active', true)->count()],
            ['label' => 'Active testimonials', 'n' => Testimonial::active()->count()],
            ['label' => 'Images', 'n' => Media::count()],
            ['label' => 'New enquiries', 'n' => Enquiry::whereNull('handled_at')->count()],
        ];
    }

    /**
     * Pages and articles together, most recent first. Split into two lists it would be impossible
     * to see what was touched last, which is the only question this card answers.
     */
    private function recentlyEdited(): array
    {
        $pages = Page::orderByDesc('updated_at')->limit(6)->get()->map(fn (Page $p) => [
            'kind' => 'page',
            'title' => $p->title,
            'sub' => $p->url,
            'href' => '/cms/pages/'.$p->cms_id.'/edit',
            'status' => $p->draft !== null && $p->status === 'published' ? 'changes' : $p->status,
            'by' => $p->last_updated_by ?? $p->published_by,
            'at' => $p->updated_at?->toIso8601String(),
        ]);

        $articles = BlogPost::orderByDesc('updated_at')->limit(6)->get()->map(fn (BlogPost $a) => [
            'kind' => 'article',
            'title' => $a->title,
            'sub' => $a->url(),
            'href' => '/cms/blog/'.$a->id.'/edit',
            'status' => $a->status,
            'by' => $a->last_updated_by,
            'at' => $a->updated_at?->toIso8601String(),
        ]);

        return $pages->concat($articles)->sortByDesc('at')->take(6)->values()->all();
    }

    /**
     * What is written but not live. A published page with a draft belongs here too — the change is
     * finished and nobody has pressed publish, which is exactly the thing that gets forgotten.
     */
    private function awaitingPublication(): array
    {
        $pages = Page::where(fn ($q) => $q->where('status', 'draft')->orWhereNotNull('draft'))
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Page $p) => [
                'title' => $p->title,
                'sub' => $p->status === 'published' ? 'Live, with unpublished changes' : 'Never published',
                'href' => '/cms/pages/'.$p->cms_id.'/edit',
                'at' => $p->updated_at?->toIso8601String(),
            ]);

        $articles = BlogPost::where('status', 'draft')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (BlogPost $a) => [
                'title' => $a->title,
                'sub' => 'Draft article',
                'href' => '/cms/blog/'.$a->id.'/edit',
                'at' => $a->updated_at?->toIso8601String(),
            ]);

        return $pages->concat($articles)->sortByDesc('at')->take(8)->values()->all();
    }

    private function recentlyPublished(): array
    {
        return BlogPost::where('status', 'published')
            ->orderByDesc('published_at')
            ->limit(5)
            ->get()
            ->map(fn (BlogPost $a) => [
                'title' => $a->title,
                'sub' => $a->published_at?->format('j F Y') ?? 'No date',
                'href' => '/cms/blog/'.$a->id.'/edit',
                'url' => $a->url(),
            ])
            ->all();
    }

    /** The audit log from §13, read rather than invented. */
    private function activity(): array
    {
        return Activity::latest('id')->limit(6)->get()->map(fn (Activity $a) => [
            'who' => $a->by_name ?? 'System',
            'action' => $a->action,
            'subject' => $a->subjectLabel(),
            'type' => $a->subject_type,
            'at' => $a->created_at?->toIso8601String(),
        ])->all();
    }

    /**
     * The line under the greeting. It said "three pages have unpublished changes and two articles
     * are waiting for review" no matter what was true, including on an empty site.
     */
    private function summary(): string
    {
        $waiting = Page::whereNotNull('draft')->where('status', 'published')->count();
        $drafts = Page::where('status', 'draft')->count() + BlogPost::where('status', 'draft')->count();
        $enquiries = Enquiry::whereNull('handled_at')->count();

        $parts = array_filter([
            $waiting > 0 ? $this->plural($waiting, 'page', 'pages').' with unpublished changes' : null,
            $drafts > 0 ? $this->plural($drafts, 'draft', 'drafts').' not yet live' : null,
            $enquiries > 0 ? $this->plural($enquiries, 'new enquiry', 'new enquiries') : null,
        ]);

        return $parts === []
            ? 'Everything written is published, and there is nothing waiting.'
            : ucfirst(implode(', ', $parts)).'.';
    }

    private function plural(int $n, string $one, string $many): string
    {
        return $n.' '.($n === 1 ? $one : $many);
    }
}
