<?php

namespace App\Http\Requests;

use App\Content\PageContentStore;
use App\Content\Text;
use App\Content\ValidatesSectionTree;
use App\Models\Page;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A page file from another site — normally one this CMS exported, though a seed file from
 * `resources/content/pages/` is accepted too, since it carries the same tree under `published`.
 *
 * The file is decoded and sanitised before the rules run, so what is validated is exactly what will be
 * stored: the tree goes through the same rules a draft save does, and nothing in it arrives as markup.
 */
class ImportPageRequest extends FormRequest
{
    use SeoFieldRules;
    use ValidatesSectionTree;

    public const MAX_KB = 512;

    private const SLUG = '/^[a-z0-9]+(?:-[a-z0-9]+)*(?:\/[a-z0-9]+(?:-[a-z0-9]+)*)*$/';

    protected function prepareForValidation(): void
    {
        $this->merge(['document' => $this->decode()]);
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:'.self::MAX_KB, 'extensions:json'],
            'document' => ['required', 'array'],
            'document.slug' => ['required', 'string', 'max:190', 'regex:'.self::SLUG],
            'document.title' => ['required', 'string', 'max:160'],
            'document.navLabel' => ['nullable', 'string', 'max:160'],
            'document.seo' => ['array'],
            ...$this->seoRules('document.seo.'),
            ...$this->sectionTreeRules('document.sections'),
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Choose a page file to import.',
            'file.extensions' => 'Choose a .json file — the kind “Download as file” saves.',
            'file.max' => 'That file is too large to be a page.',
            'document.required' => 'That file isn’t a page exported from this CMS.',
            'document.slug.required' => 'The file doesn’t say which web address the page belongs at.',
            'document.slug.regex' => 'The web address in the file can only use lowercase letters, numbers and hyphens.',
            'document.title.required' => 'The file doesn’t give the page a name.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $document = $this->input('document');

            if (! is_array($document)) {
                return;
            }

            if (is_array($document['sections'] ?? null)) {
                $this->checkSectionTree($validator, $document['sections'], 'document.sections');
            }

            $slug = $document['slug'] ?? null;

            if (! is_string($slug) || $validator->errors()->has('document.slug')) {
                return;
            }

            if (PageContentStore::slugIsReserved($slug)) {
                $validator->errors()->add('document.slug', "The web address /{$slug} is reserved, so this page can’t be imported.");
            } elseif (Page::where('slug', $slug)->exists()) {
                $validator->errors()->add(
                    'document.slug',
                    "A page at /{$slug} already exists on this site, so nothing was imported. Rename that page first if this one should replace it.",
                );
            } elseif (str_contains($slug, '/') && ! Page::where('slug', dirname($slug))->exists()) {
                $validator->errors()->add('document.slug', 'This page belongs under /'.dirname($slug).', which doesn’t exist on this site yet.');
            }
        });
    }

    public function document(): array
    {
        $document = $this->validated()['document'];

        return [
            'slug' => $document['slug'],
            'title' => $document['title'],
            'navLabel' => $document['navLabel'] ?? null,
            'seo' => $document['seo'] ?? [],
            'sections' => $document['sections'],
        ];
    }

    /** Null for anything that is not a page file, which the `document.required` message then explains. */
    private function decode(): ?array
    {
        $file = $this->file('file');

        if ($file === null || ! $file->isValid() || $file->getSize() > self::MAX_KB * 1024) {
            return null;
        }

        $raw = json_decode((string) file_get_contents($file->getRealPath()), true);

        if (! is_array($raw) || ! isset($raw['slug']) || (isset($raw['format']) && $raw['format'] !== PageContentStore::TRANSFER_FORMAT)) {
            return null;
        }

        $sections = $raw['sections'] ?? $raw['draft'] ?? $raw['published'] ?? null;

        if (! is_array($sections)) {
            return null;
        }

        $seo = is_array($raw['seo'] ?? null) ? $raw['seo'] : [];

        foreach (['title', 'description', 'image', 'canonical'] as $key) {
            if (array_key_exists($key, $seo)) {
                $seo[$key] = Text::clean($seo[$key]);
            }
        }

        return [
            'slug' => is_string($raw['slug']) ? trim($raw['slug'], '/') : $raw['slug'],
            'title' => Text::clean($raw['title'] ?? null),
            'navLabel' => Text::clean($raw['navLabel'] ?? null),
            'seo' => $seo,
            'sections' => $this->sanitiseTree($sections),
        ];
    }
}
