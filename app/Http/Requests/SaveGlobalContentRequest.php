<?php

namespace App\Http\Requests;

use App\Content\Text;
use Illuminate\Foundation\Http\FormRequest;

class SaveGlobalContentRequest extends FormRequest
{
    /** Stripped before the rules run, so `required` judges what will really be stored (§14). */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'notice' => [
                'active' => $this->boolean('notice.active'),
                'text' => Text::clean($this->input('notice.text')),
                'href' => Text::clean($this->input('notice.href')),
            ],
            /* Only the wording. The logo is drawn inline by `BrandMark`, not fetched, so there is no
               image address for an editor to change — see `logo()`. */
            'logo' => ['alt' => Text::clean($this->input('logo.alt'))],
            'phone' => ['label' => Text::clean($this->input('phone.label'))],
            'cta' => ['label' => Text::clean($this->input('cta.label'))],
            'footer' => [
                'word' => Text::clean($this->input('footer.word')),
                'blurb' => Text::clean($this->input('footer.blurb')),
                'address' => Text::clean($this->input('footer.address')),
                'legal' => Text::clean($this->input('footer.legal')),
            ],
        ]);
    }

    /**
     * The announcement's wording is only required while it is showing. Demanding it when the bar is
     * switched off would make turning it off impossible without first inventing something to say.
     */
    public function rules(): array
    {
        return [
            'notice.active' => ['boolean'],
            'notice.text' => ['nullable', 'required_if:notice.active,true', 'string', 'max:120'],
            'notice.href' => ['nullable', 'string', 'max:200'],

            'logo.alt' => ['required', 'string', 'max:120'],

            'phone.label' => ['required', 'string', 'max:40'],
            'cta.label' => ['required', 'string', 'max:40'],

            'footer.word' => ['required', 'string', 'max:60'],
            'footer.blurb' => ['required', 'string', 'max:400'],
            'footer.address' => ['nullable', 'string', 'max:200'],
            'footer.legal' => ['required', 'string', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return [
            'notice.text.required_if' => 'The announcement bar needs something to say while it is showing.',
            'logo.alt.required' => 'The logo needs wording for readers using a screen reader.',
            'phone.label.required' => 'The phone number cannot be blank — it is the main way people make contact.',
        ];
    }

    public function notice(): array
    {
        $notice = $this->validated()['notice'];

        return [
            'active' => (bool) $notice['active'],
            'text' => $notice['text'],
            'href' => $notice['href'] ?: '#',
        ];
    }

    /**
     * The description alone, and never the image address.
     *
     * The lockup is an inline `<svg>` compiled into the bundle (`BrandMark`), so `logo.src` never
     * chose the artwork — it only decided, by string equality against one magic path, whether to draw
     * inline or fall back to an `<img>`. Offered as a field it was worse than useless: a wide upload
     * blows out the header, which `.brand .mark` has no `max-width` to stop. The description is real
     * content, and feeds both branches.
     */
    public function logo(): array
    {
        return ['alt' => $this->validated()['logo']['alt']];
    }

    /**
     * The dialling link is derived, never typed. Editing the number and leaving a `tel:` pointing at
     * the old one is a silent fault — the header would read correctly and dial somebody else.
     */
    public function phone(): array
    {
        $label = $this->validated()['phone']['label'];
        $digits = preg_replace('/[^0-9+]/', '', $label);

        return [
            'label' => $label,
            'href' => $digits === '' ? null : 'tel:'.$digits,
        ];
    }

    public function ctaLabel(): string
    {
        return $this->validated()['cta']['label'];
    }

    /** Address lines are typed one per line; blank lines are dropped rather than stored as gaps. */
    public function footer(): array
    {
        $footer = $this->validated()['footer'];

        $address = array_values(array_filter(
            array_map('trim', preg_split('/\R/', (string) $footer['address'])),
            fn ($line) => $line !== '',
        ));

        return [
            'word' => $footer['word'],
            'blurb' => $footer['blurb'],
            'address' => $address,
            'legal' => $footer['legal'],
        ];
    }
}
