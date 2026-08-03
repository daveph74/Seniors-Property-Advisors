<?php

namespace App\Http\Requests;

use App\Content\Site;
use App\Content\Text;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSettingsRequest extends FormRequest
{
    /** Stripped before the rules run, so `required` judges what will really be stored (§14). */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => Text::clean($this->input('name')),
            'favicon' => Text::clean($this->input('favicon')),
            'seo' => [
                'titleFormat' => Text::clean($this->input('seo.titleFormat')),
                'description' => Text::clean($this->input('seo.description')),
                'image' => Text::clean($this->input('seo.image')),
            ],
            'social' => [
                'facebook' => Text::clean($this->input('social.facebook')),
                'linkedin' => Text::clean($this->input('social.linkedin')),
            ],
            /* Upper-cased so a pasted "g-abc123" is accepted rather than rejected on a detail
               nobody can see. Google's ids are case-insensitive in practice. */
            'tracking' => [
                'ga4' => strtoupper((string) Text::clean($this->input('tracking.ga4'))) ?: null,
                'gtm' => strtoupper((string) Text::clean($this->input('tracking.gtm'))) ?: null,
            ],
            'legal' => [
                'disclaimer' => Text::clean($this->input('legal.disclaimer')),
                'privacyPage' => $this->input('legal.privacyPage') ?: null,
            ],
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'favicon' => ['nullable', 'string', 'max:300', 'regex:#^/#'],

            'seo.titleFormat' => ['nullable', 'string', 'max:120'],
            'seo.description' => ['nullable', 'string', 'max:320'],
            'seo.image' => ['nullable', 'string', 'max:400', 'regex:#^/#'],

            /* A full profile address, so the footer link cannot be a relative path into this site.
               Stored as typed otherwise — shortening or rewriting somebody's URL is not our business. */
            'social.facebook' => ['nullable', 'url', 'max:300'],
            'social.linkedin' => ['nullable', 'url', 'max:300'],

            'tracking.ga4' => ['nullable', 'string', 'regex:'.Site::GA4],
            'tracking.gtm' => ['nullable', 'string', 'regex:'.Site::GTM],

            'legal.disclaimer' => ['nullable', 'string', 'max:600'],
            /* Published only: a consent line linking to a draft is a 404 at the moment somebody is
               being asked to agree to it. */
            'legal.privacyPage' => [
                'nullable',
                'integer',
                Rule::exists('pages', 'id')->where('status', 'published'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The website needs a name — it is used in page titles and search results.',
            'tracking.ga4.regex' => 'A Google Analytics 4 id looks like G-XXXXXXXXXX.',
            'tracking.gtm.regex' => 'A Google Tag Manager id looks like GTM-XXXXXXX.',
            'favicon.regex' => 'Choose an image from the media library.',
            'seo.image.regex' => 'Choose an image from the media library.',
            'legal.privacyPage.exists' => 'Choose a page that is on the website. A draft would be a dead link.',
            'social.facebook.url' => 'Enter the full web address, starting with https://.',
            'social.linkedin.url' => 'Enter the full web address, starting with https://.',
        ];
    }

    /** The stored shape, which is also what `Site` reads. */
    public function settings(): array
    {
        $valid = $this->validated();

        return [
            'name' => $valid['name'],
            'favicon' => $valid['favicon'] ?: null,
            'seo' => [
                'titleFormat' => $valid['seo']['titleFormat'] ?: null,
                'description' => $valid['seo']['description'] ?: null,
                'image' => $valid['seo']['image'] ?: null,
            ],
            'social' => [
                'facebook' => $valid['social']['facebook'] ?: null,
                'linkedin' => $valid['social']['linkedin'] ?: null,
            ],
            'tracking' => [
                'ga4' => $valid['tracking']['ga4'] ?: null,
                'gtm' => $valid['tracking']['gtm'] ?: null,
            ],
            'legal' => [
                'disclaimer' => $valid['legal']['disclaimer'] ?: null,
                'privacyPage' => isset($valid['legal']['privacyPage'])
                    ? (int) $valid['legal']['privacyPage']
                    : null,
            ],
        ];
    }
}
