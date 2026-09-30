<?php

namespace App\Http\Requests;

use App\Content\PageContentStore;
use App\Content\ValidatesSectionTree;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class SaveSectionsRequest extends FormRequest
{
    use ValidatesSectionTree;

    public function rules(): array
    {
        return $this->sectionTreeRules('sections');
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $sections = $this->input('sections');

            if (is_array($sections)) {
                $this->checkSectionTree($validator, $sections, 'sections');
            }
        });
    }

    public function sections(): array
    {
        return $this->sanitiseTree($this->validated()['sections']);
    }

    protected function storedCustomCss(): array
    {
        $cmsId = $this->route('page');

        if ($cmsId === null) {
            return [];
        }

        $slug = app(PageContentStore::class)->findByCmsId((int) $cmsId);

        if ($slug === null) {
            return [];
        }

        $document = app(PageContentStore::class)->document($slug);

        return $this->customCssIn($document['published'] ?? []) + $this->customCssIn($document['draft'] ?? []);
    }
}
