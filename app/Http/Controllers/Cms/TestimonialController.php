<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\Testimonial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TestimonialController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Cms/Testimonials/Index', [
            'testimonials' => Testimonial::ordered()->get()->map(fn (Testimonial $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'quote' => $t->quote,
                'location' => $t->location,
                'headline' => $t->headline,
                'image' => $t->image,
                'rating' => $t->rating,
                'featured' => $t->featured,
                'active' => $t->active,
                'hasConsent' => $t->hasConsent(),
                'consentBy' => $t->consent_confirmed_by,
                'consentAt' => $t->consent_confirmed_at?->toDateString(),
                'updatedBy' => $t->last_updated_by,
            ])->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Testimonial::create($this->validated($request) + [
            'sort_order' => (int) Testimonial::max('sort_order') + 1,
            'last_updated_by' => $this->author(),
        ]);

        return back();
    }

    public function update(Request $request, Testimonial $testimonial): RedirectResponse
    {
        $testimonial->update($this->validated($request, $testimonial) + ['last_updated_by' => $this->author()]);

        return back();
    }

    public function destroy(Testimonial $testimonial): RedirectResponse
    {
        $testimonial->delete();

        return back();
    }

    public function reorder(Request $request): RedirectResponse
    {
        foreach ((array) $request->input('ids', []) as $position => $id) {
            Testimonial::whereKey($id)->update(['sort_order' => $position + 1]);
        }

        return back();
    }

    /**
     * Confirming permission is its own action, deliberately: it records who said so and when,
     * which a checkbox alongside the wording would not. Withdrawing it takes the testimonial off
     * the website in the same move, because that is the only reason to withdraw it.
     */
    public function consent(Request $request, Testimonial $testimonial): RedirectResponse
    {
        $given = $request->boolean('confirmed');

        $testimonial->update([
            'consent_confirmed_at' => $given ? now() : null,
            'consent_confirmed_by' => $given ? $this->author() : null,
            'active' => $given ? $testimonial->active : false,
            'featured' => $given ? $testimonial->featured : false,
            'last_updated_by' => $this->author(),
        ]);

        return back();
    }

    /**
     * Publishing is refused rather than silently ignored: an editor who ticks "active" on a
     * testimonial with no recorded permission has made a mistake worth telling them about.
     */
    private function validated(Request $request, ?Testimonial $existing = null): array
    {
        /*
         * On an update every field is optional-but-valid-if-sent, so a single toggle can patch one
         * field alone. Sending the whole record for a one-field change means any stale copy of it
         * silently reverts whatever else moved in between — which is exactly what two quick
         * toggles did before this.
         */
        $needed = $existing === null ? ['required'] : ['sometimes', 'required'];

        $data = $request->validate([
            'name' => [...$needed, 'string', 'max:190'],
            'quote' => [...$needed, 'string', 'max:2000'],
            'location' => ['nullable', 'string', 'max:190'],
            'headline' => ['nullable', 'string', 'max:190'],
            'image' => ['nullable', 'string', 'max:500'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'featured' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
        ]);

        foreach (['name', 'quote', 'location', 'headline'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = trim(strip_tags((string) $data[$field])) ?: null;
            }
        }

        $wantsPublishing = ($data['active'] ?? false) || ($data['featured'] ?? false);

        if ($wantsPublishing && $existing?->hasConsent() !== true) {
            throw ValidationException::withMessages([
                'active' => 'Record the client’s permission before showing this on the website.',
            ]);
        }

        return $data;
    }

    private function author(): string
    {
        return (string) request()->user()->name;
    }
}
