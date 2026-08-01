<?php

namespace App\Http\Requests;

use App\Content\PageContentStore;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class SaveSectionsRequest extends FormRequest
{
    public const MAX_TIERS = 6;

    public function rules(): array
    {
        $rules = ['sections' => ['present', 'array']];
        $prefix = 'sections.*';

        for ($tier = 0; $tier < self::MAX_TIERS; $tier++) {
            $rules += $this->blockRules($prefix);
            $prefix .= '.children.*';
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $sections = $this->input('sections');

            if (is_array($sections)) {
                $this->checkTree($validator, $sections, 'sections', PageContentStore::SECTION_TYPES);
            }
        });
    }

    public function sections(): array
    {
        return $this->sanitise($this->validated()['sections']);
    }

    private function blockRules(string $prefix): array
    {
        return [
            "{$prefix}.id" => ['required', 'string', 'max:120'],
            "{$prefix}.type" => ['required', 'string'],
            "{$prefix}.label" => ['required', 'string', 'max:120'],
            "{$prefix}.active" => ['boolean'],
            "{$prefix}.anchor" => ['nullable', 'string', 'max:120'],
            "{$prefix}.data" => ['present', 'array'],
            "{$prefix}.children" => ['sometimes', 'array'],
        ];
    }

    private function checkTree(Validator $validator, array $items, string $path, array $allowed, int $depth = 0): void
    {
        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $type = $item['type'] ?? null;

            if (! is_string($type) || ! in_array($type, $allowed, true)) {
                $validator->errors()->add(
                    "{$path}.{$index}.type",
                    $path === 'sections'
                        ? "\"{$type}\" is not a section type this page can render yet. Remove it before saving."
                        : "\"{$type}\" cannot be placed inside this container.",
                );

                continue;
            }

            $next = $type === 'row' ? $depth + 1 : $depth;

            if ($next > PageContentStore::MAX_ROW_DEPTH) {
                $validator->errors()->add(
                    "{$path}.{$index}.type",
                    'Rows cannot be nested more than '.PageContentStore::MAX_ROW_DEPTH.' levels deep.',
                );

                continue;
            }

            $children = $item['children'] ?? null;

            if (! is_array($children) || $children === []) {
                continue;
            }

            $allowedChildren = PageContentStore::CHILD_TYPES[$type] ?? [];

            if ($allowedChildren === []) {
                $validator->errors()->add(
                    "{$path}.{$index}.children",
                    "A \"{$type}\" block cannot contain other blocks.",
                );

                continue;
            }

            $this->checkTree($validator, $children, "{$path}.{$index}.children", $allowedChildren, $next);
        }
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
