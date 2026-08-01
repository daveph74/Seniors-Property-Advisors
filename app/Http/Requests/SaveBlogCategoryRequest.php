<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveBlogCategoryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['name.required' => 'A category needs a name.'];
    }

    public function name(): string
    {
        return trim(strip_tags($this->validated()['name']));
    }

    public function active(): bool
    {
        return $this->boolean('active', true);
    }
}
