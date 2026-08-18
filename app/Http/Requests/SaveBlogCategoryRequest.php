<?php

namespace App\Http\Requests;

use App\Content\Text;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBlogCategoryRequest extends FormRequest
{
    /* Sanitised before the rules see it, so "<hr>" cannot pass `required` and then be saved empty. */
    protected function prepareForValidation(): void
    {
        $this->merge(Text::cleanAll($this->only('name'), ['name']));
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:120',
                /* Two categories called Downsizing broke the filter on the blog screen, which keys
                   its dropdown by name, and gave a reader two identical chips. */
                Rule::unique('blog_categories', 'name')->ignore($this->route('category')),
            ],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'A category needs a name.',
            'name.unique' => 'There is already a category with that name.',
        ];
    }

    public function name(): string
    {
        return (string) $this->validated()['name'];
    }

    public function active(): bool
    {
        return $this->boolean('active', true);
    }
}
