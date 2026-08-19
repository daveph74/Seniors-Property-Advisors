<?php

namespace App\Http\Requests;

use App\Content\Text;
use Illuminate\Foundation\Http\FormRequest;

/**
 * One address's SEO fields, patched from the overview.
 *
 * Deliberately narrow: it carries the description and the hide-from-search switch and nothing else,
 * because fixing twenty missing descriptions should not mean twenty trips into the builder — and
 * because the two fields it does not carry are the two that can quietly cost a page its traffic. A
 * canonical typed into a list is an address de-indexed by a fat finger, so that one stays in the
 * builder's SEO panel where there is room to explain it.
 *
 * `sometimes` on both, so a toggle patches one field alone. The rule is the one the testimonials
 * screen already pays for: send the whole record on a single-field change and a stale copy reverts
 * whatever else moved between the page loading and the switch being pressed.
 */
class SaveSeoFieldsRequest extends FormRequest
{
    use SeoFieldRules;

    protected function prepareForValidation(): void
    {
        if ($this->has('description')) {
            $this->merge(['description' => Text::clean($this->input('description'))]);
        }
    }

    public function rules(): array
    {
        return [
            'description' => ['sometimes', 'nullable', 'string', 'max:'.self::DESCRIPTION_MAX],
            'noindex' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'description.max' => 'A description can be at most '.self::DESCRIPTION_MAX.' characters.',
        ];
    }

    /**
     * Only what arrived. An absent key means "leave it alone", and an empty description means
     * "clear it" — which `PageContentStore::saveDetails()` then drops from the stored array so the
     * site default takes over again.
     *
     * @return array<string, mixed>
     */
    public function fields(): array
    {
        $valid = $this->validated();
        $seo = [];

        if (array_key_exists('description', $valid)) {
            $seo['description'] = $valid['description'] ?: null;
        }

        if (array_key_exists('noindex', $valid)) {
            /* Kept as a real false rather than dropped: un-hiding a page has to overwrite the
               stored true, and a filtered-out false would merge the old value straight back. */
            $seo['noindex'] = (bool) $valid['noindex'];
        }

        return $seo;
    }
}
