<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SavePageDetailsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'navLabel' => ['nullable', 'string', 'max:60'],
            'slug' => ['nullable', 'string', 'max:190', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*(?:\/[a-z0-9]+(?:-[a-z0-9]+)*)*$/'],
            'seo' => ['sometimes', 'array'],
            'seo.title' => ['nullable', 'string', 'max:160'],
            'seo.description' => ['nullable', 'string', 'max:320'],
            'seo.image' => ['nullable', 'string', 'max:400'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'A page needs a name.',
            'slug.regex' => 'A web address can only use lowercase letters, numbers and hyphens.',
        ];
    }

    public function title(): string
    {
        return trim(strip_tags($this->validated()['title']));
    }

    /**
     * Null means "not sent, leave it alone"; an empty string means "clear it".
     * The topbar saves only the title, and must not wipe the menu label.
     */
    public function navLabel(): ?string
    {
        if (! $this->has('navLabel')) {
            return null;
        }

        return trim(strip_tags((string) ($this->validated()['navLabel'] ?? '')));
    }

    public function slug(): ?string
    {
        $slug = $this->validated()['slug'] ?? null;

        return $slug === null ? null : (trim($slug, '/') ?: null);
    }

    public function seo(): array
    {
        $seo = $this->validated()['seo'] ?? [];

        return array_map(
            fn ($value) => is_string($value) ? (trim(strip_tags($value)) ?: null) : $value,
            $seo,
        );
    }
}
