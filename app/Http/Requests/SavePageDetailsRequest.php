<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SavePageDetailsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'seo' => ['sometimes', 'array'],
            'seo.title' => ['nullable', 'string', 'max:160'],
            'seo.description' => ['nullable', 'string', 'max:320'],
        ];
    }

    public function messages(): array
    {
        return ['title.required' => 'A page needs a name.'];
    }

    public function title(): string
    {
        return trim(strip_tags($this->validated()['title']));
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
