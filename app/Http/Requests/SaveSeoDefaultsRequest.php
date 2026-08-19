<?php

namespace App\Http\Requests;

use App\Content\Text;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The site-wide SEO defaults, which used to be a tab on `/cms/settings`.
 *
 * They moved because they belong beside the report that shows what they do: a default description
 * is invisible until you can see the twelve addresses inheriting it. Settings keeps everything a
 * client administrator should not see — the analytics ids, the legal wording — and its own ability.
 *
 * Only the `seo` key is sent, and `Site::merge()` leaves the rest of the row alone. The screen has
 * no tracking ids to send, so a writer that submitted the whole row would clear them.
 */
class SaveSeoDefaultsRequest extends FormRequest
{
    use SeoFieldRules;

    /** Stripped before the rules run, so a limit judges what will really be stored (§14). */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'titleFormat' => Text::clean($this->input('titleFormat')),
            'description' => Text::clean($this->input('description')),
            'image' => Text::clean($this->input('image')),
        ]);
    }

    public function rules(): array
    {
        return [
            'titleFormat' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:'.self::DESCRIPTION_MAX],
            'image' => ['nullable', 'string', 'max:'.self::IMAGE_MAX, 'regex:#^/#'],
        ];
    }

    public function messages(): array
    {
        return [
            'image.regex' => 'Choose an image from the media library.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->refuseSvgImage($validator, 'image'));
    }

    /** @return array{titleFormat: string|null, description: string|null, image: string|null} */
    public function defaults(): array
    {
        $valid = $this->validated();

        return [
            'titleFormat' => $valid['titleFormat'] ?: null,
            'description' => $valid['description'] ?: null,
            'image' => $valid['image'] ?: null,
        ];
    }
}
