<?php

namespace App\Http\Requests;

use App\Content\PageContentStore;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSectionsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'sections' => ['present', 'array'],
            'sections.*.id' => ['required', 'string', 'max:120'],
            'sections.*.type' => ['required', 'string', Rule::in(PageContentStore::SECTION_TYPES)],
            'sections.*.label' => ['required', 'string', 'max:120'],
            'sections.*.active' => ['boolean'],
            'sections.*.anchor' => ['nullable', 'string', 'max:120'],
            'sections.*.data' => ['present', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'sections.*.type' => '":input" is not a section type this page can render yet. Remove it before saving.',
        ];
    }

    public function sections(): array
    {
        return $this->sanitise($this->validated()['sections']);
    }

    private function sanitise(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->sanitise($item);
            } elseif (is_string($item)) {
                $value[$key] = strip_tags($item);
            }
        }

        return $value;
    }
}
