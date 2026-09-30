<?php

namespace App\Http\Requests;

use App\Content\PageContentStore;
use App\Content\ValidatesSectionTree;
use App\Models\ReusableSection;
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
        $found = [];

        foreach (ReusableSection::query()->pluck('block') as $block) {
            $found = [...$found, ...$this->customCssIn([$block])];
        }

        $cmsId = $this->route('page');
        $slug = is_scalar($cmsId) ? app(PageContentStore::class)->findByCmsId((string) $cmsId) : null;

        if ($slug === null) {
            return array_values(array_unique($found));
        }

        $document = app(PageContentStore::class)->document($slug);

        return array_values(array_unique([
            ...$found,
            ...$this->customCssIn($document['draft'] ?? []),
            ...$this->customCssIn($document['published'] ?? []),
        ]));
    }
}
