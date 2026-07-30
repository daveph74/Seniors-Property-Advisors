<?php

namespace App\Http\Requests;

class SaveReusableSectionRequest extends SaveSectionsRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'name' => ['required', 'string', 'max:120'],
            'sections' => ['present', 'array', 'size:1'],
        ]);
    }

    public function name(): string
    {
        return trim(strip_tags($this->validated()['name']));
    }

    public function block(): array
    {
        return $this->sections()[0];
    }
}
