<?php

namespace App\Http\Requests;

use App\Content\Text;
use App\Enquiries\FindMyAgentOptions;
use App\Models\Enquiry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * What a visitor is allowed to send, and what is kept.
 *
 * Scope §12 draws a line here: the wording around these forms is the CMS's, but the fields, the
 * validation and wherever an enquiry eventually goes are the development team's, and a CMS user must
 * not be able to change them. So they live here, and none of it is editable through the admin — only
 * the heading, intro, consent wording and confirmation message are.
 *
 * Both forms come through this one request, and deliberately: the public endpoint is rate limited
 * (`throttle:6,1`), and a second route for the second form would be a public write path that
 * `OwaspTest` does not know exists. What differs between them is a block of rules that applies only
 * when the payload says it came from Find My Agent — not an endpoint of its own.
 *
 * Nothing stored here is ever rendered as markup, so the HTML purifier a blog body goes through does
 * not apply; `Text::clean` strips tags and that is the whole of it.
 */
class StoreEnquiryRequest extends FormRequest
{
    public function rules(): array
    {
        return array_merge([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => [$this->fromWizard() ? 'required' : 'nullable', 'string', 'max:40'],
            'suburb' => ['nullable', 'string', 'max:120'],
            'message' => ['nullable', 'string', 'max:4000'],
            'consent' => ['accepted'],
            'page' => ['nullable', 'string', 'max:190'],
            /* A label, and only ever one this application knows. Nothing security-relevant reads it. */
            'source' => ['nullable', Rule::in(array_keys(Enquiry::SOURCES))],
        ], $this->fromWizard() ? $this->wizardRules() : []);
    }

    /**
     * Enumerated key by key rather than validated as one `array`, which is what lets `toEnquiry()`
     * build `details` out of `validated()` alone — anything else the browser sends is not merely
     * ignored, it is impossible to store.
     */
    private function wizardRules(): array
    {
        return [
            'details' => ['required', 'array'],
            'details.property_type' => ['required', Rule::in(array_keys(FindMyAgentOptions::PROPERTY_TYPES))],
            'details.timeline' => ['required', Rule::in(array_keys(FindMyAgentOptions::TIMELINES))],
            'details.best_time' => ['required', Rule::in(array_keys(FindMyAgentOptions::BEST_TIMES))],
            'details.location' => ['required', 'array'],
            'details.location.suburb' => ['required', 'string', 'max:120'],
            'details.location.state' => ['nullable', 'string', 'max:40'],
            'details.location.postcode' => ['nullable', 'string', 'max:12'],
            'details.location.place_id' => ['nullable', 'string', 'max:300'],
            'details.location.description' => ['nullable', 'string', 'max:300'],
            'details.location.lat' => ['nullable', 'numeric', 'between:-90,90'],
            'details.location.lng' => ['nullable', 'numeric', 'between:-180,180'],
            'details.location.free_text' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'consent.accepted' => 'Please tick the box to say we may contact you.',
            'email.email' => 'That email address does not look right.',
            'phone.required' => 'Enter a phone number we can reach you on.',
            'details.property_type.required' => 'Choose the type of property you have.',
            'details.property_type.in' => 'Choose one of the property types listed.',
            'details.timeline.required' => 'Choose when you are hoping to sell.',
            'details.timeline.in' => 'Choose one of the timings listed.',
            'details.best_time.required' => 'Choose the time of day that suits you best.',
            'details.best_time.in' => 'Choose one of the times listed.',
            'details.location.suburb.required' => 'Enter the suburb your property is in.',
        ];
    }

    /**
     * Sanitised before the rules run, not after — see `Text`. Validating first and stripping later is
     * how a name of "<hr>" once passed `required` and then hit the database as nothing at all.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(Text::cleanAll(
            $this->only(['name', 'email', 'phone', 'suburb', 'message', 'page', 'source']),
            ['name', 'email', 'phone', 'suburb', 'message', 'page', 'source'],
        ));

        if (is_array($this->input('details'))) {
            $this->merge(['details' => $this->cleanDetails($this->input('details'))]);
        }
    }

    private function cleanDetails(array $details): array
    {
        foreach (['property_type', 'timeline', 'best_time'] as $key) {
            if (array_key_exists($key, $details)) {
                $details[$key] = Text::clean($details[$key]);
            }
        }

        if (is_array($details['location'] ?? null)) {
            $details['location'] = Text::cleanAll(
                $details['location'],
                ['suburb', 'state', 'postcode', 'place_id', 'description'],
            );
        }

        return $details;
    }

    public function toEnquiry(): array
    {
        $data = $this->validated();
        $location = $data['details']['location'] ?? [];

        return [
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            /* The wizard asks for the suburb as its own question and resolves it against a lookup, so
               the answer is copied into the column the list, the detail header and the search already
               read. The whole resolved place stays in `details`. */
            'suburb' => $data['suburb'] ?? $location['suburb'] ?? null,
            'message' => $data['message'] ?? null,
            /* Derived from an answer rather than assumed: the rule above is what makes it true. */
            'consented' => (bool) $data['consent'],
            'page_slug' => $data['page'] ?? null,
            'source' => $data['source'] ?? Enquiry::CONTACT_FORM,
            /* Null, never an empty array — a contact-form enquiry has no answers, and `[]` would make
               the CMS render an empty heading over nothing. */
            'details' => $this->fromWizard() ? $data['details'] : null,
        ];
    }

    private function fromWizard(): bool
    {
        return $this->input('source') === Enquiry::FIND_MY_AGENT;
    }
}
