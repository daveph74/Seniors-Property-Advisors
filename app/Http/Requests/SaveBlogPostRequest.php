<?php

namespace App\Http\Requests;

use App\Content\Html;
use App\Models\BlogPost;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBlogPostRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:190'],
            'slug' => ['nullable', 'string', 'max:190', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'summary' => ['nullable', 'string', 'max:400'],
            'body' => ['nullable', 'string', 'max:200000'],
            'featured_image' => ['nullable', 'string', 'max:400'],
            'featured_image_alt' => ['nullable', 'string', 'max:300'],
            'author_name' => ['nullable', 'string', 'max:120'],
            'status' => ['sometimes', Rule::in(BlogPost::STATUSES)],
            'published_at' => ['nullable', 'date'],
            'categories' => ['sometimes', 'array'],
            'categories.*' => ['integer', 'exists:blog_categories,id'],
            'seo' => ['sometimes', 'array'],
            'seo.title' => ['nullable', 'string', 'max:160'],
            'seo.description' => ['nullable', 'string', 'max:320'],
            'seo.image' => ['nullable', 'string', 'max:400'],
            'seo.canonical' => ['nullable', 'string', 'max:400', 'url'],
            'seo.noindex' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'An article needs a title.',
            'slug.regex' => 'A web address can only use lowercase letters, numbers and hyphens.',
        ];
    }

    /**
     * Two things that would look saved and then not work.
     *
     * The media library accepts SVG, but social networks refuse it — an SVG sharing image produces no
     * preview at all, silently. And the content policy permits an image from this origin only, so a
     * picture hotlinked from somewhere else would be published and drawn for nobody. Both are refused
     * here rather than left to be discovered by whoever eventually looks at the page.
     *
     * `seo.image` is not checked for this: it becomes an `og:image`, which a social network's crawler
     * fetches server-side, and no browser content policy applies to it.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (SavePageDetailsRequest::isSvg($this->input('seo.image'))) {
                $validator->errors()->add(
                    'seo.image',
                    'A sharing image cannot be an SVG — social networks will not show it. Use a JPG or PNG.',
                );
            }

            $remote = Html::remoteImageSources($this->input('body'));

            if ($remote !== []) {
                $validator->errors()->add('body', 'An image has to be uploaded to this site rather than '
                    .'linked from somewhere else, or readers will not see it. Add it to the media '
                    .'library and insert it from there — '.implode(', ', array_slice($remote, 0, 3)));
            }

            if (Html::isRemote($this->input('featured_image'))) {
                $validator->errors()->add(
                    'featured_image',
                    'A featured image has to come from the media library, not another website.',
                );
            }
        });
    }

    public function details(): array
    {
        $data = $this->validated();

        return [
            'title' => trim(strip_tags($data['title'])),
            'summary' => $this->clean($data['summary'] ?? null),
            /*
             * Purified here, so what reaches the database is already safe to print. A writer
             * pasting from Word or a web page keeps their words and loses the markup, which is
             * what a what-you-see editor should do — no error to decipher.
             */
            'body' => Html::clean($data['body'] ?? null) ?: null,
            'featured_image' => $data['featured_image'] ?? null,
            'featured_image_alt' => $this->clean($data['featured_image_alt'] ?? null),
            'author_name' => $this->clean($data['author_name'] ?? null),
            'published_at' => $data['published_at'] ?? null,
            'seo' => array_filter([
                'title' => $this->clean($data['seo']['title'] ?? null),
                'description' => $this->clean($data['seo']['description'] ?? null),
                'image' => $data['seo']['image'] ?? null,
                'canonical' => $this->clean($data['seo']['canonical'] ?? null),
                /* Stored only when switched on, so an ordinary article carries no noindex at all. */
                'noindex' => ($data['seo']['noindex'] ?? false) ? true : null,
            ], fn ($value) => $value !== null),
        ];
    }

    public function categories(): ?array
    {
        return $this->has('categories') ? array_map('intval', $this->validated()['categories'] ?? []) : null;
    }

    public function slug(): ?string
    {
        $slug = $this->validated()['slug'] ?? null;

        return $slug === null ? null : (trim($slug, '-/') ?: null);
    }

    public function status(): ?string
    {
        return $this->validated()['status'] ?? null;
    }

    private function clean(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return trim(strip_tags($value)) ?: null;
    }
}
