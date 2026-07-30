<?php

namespace App\Http\Requests;

use App\Content\PageContentStore;
use App\Content\StarterLayouts;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CreatePageRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'parent' => ['nullable', 'string', 'max:200', 'exists:pages,slug'],
            'layout' => ['nullable', 'string', Rule::in(array_keys(StarterLayouts::OPTIONS))],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Give the page a name.',
            'parent.exists' => 'That parent page no longer exists.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $slug = Str::slug((string) $this->input('title'));

            if ($slug === '') {
                $validator->errors()->add('title', 'Give the page a name that includes letters or numbers.');

                return;
            }

            if (in_array($slug, PageContentStore::RESERVED_SLUGS, true)) {
                $validator->errors()->add('title', 'That page name is reserved. Please choose another.');
            }
        });
    }

    public function title(): string
    {
        return trim(strip_tags($this->validated()['title']));
    }

    public function parent(): ?string
    {
        return $this->validated()['parent'] ?? null;
    }

    public function layout(): string
    {
        return $this->validated()['layout'] ?? 'blank';
    }
}
