<?php

namespace App\Content;

use Illuminate\Contracts\Validation\Validator;

/**
 * The one set of rules a section tree has to meet, whichever door it arrives through — a draft save
 * from the builder or a page file uploaded from another site. Two copies would be two opinions about
 * which pages are legal, and the looser one would be the way round the stricter.
 */
trait ValidatesSectionTree
{
    public const MAX_TIERS = 6;

    protected function sectionTreeRules(string $root): array
    {
        $rules = [$root => ['present', 'array']];
        $prefix = "{$root}.*";

        for ($tier = 0; $tier < self::MAX_TIERS; $tier++) {
            $rules += [
                "{$prefix}.id" => ['required', 'string', 'max:120'],
                "{$prefix}.type" => ['required', 'string'],
                "{$prefix}.label" => ['required', 'string', 'max:120'],
                "{$prefix}.active" => ['boolean'],
                "{$prefix}.anchor" => ['nullable', 'string', 'max:120'],
                "{$prefix}.data" => ['present', 'array'],
                "{$prefix}.children" => ['sometimes', 'array'],
            ];
            $prefix .= '.children.*';
        }

        return $rules;
    }

    protected function checkSectionTree(Validator $validator, array $items, string $root): void
    {
        $this->checkTier($validator, $items, $root, $root, PageContentStore::SECTION_TYPES);
    }

    protected function sanitiseTree(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->sanitiseTree($item);
            } elseif (is_string($item)) {
                $value[$key] = strip_tags($item);
            }
        }

        return $value;
    }

    private function checkTier(Validator $validator, array $items, string $root, string $path, array $allowed, int $depth = 0): void
    {
        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $type = $item['type'] ?? null;

            if (! is_string($type) || ! in_array($type, $allowed, true)) {
                $validator->errors()->add(
                    "{$path}.{$index}.type",
                    $path === $root
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

            $this->checkTier($validator, $children, $root, "{$path}.{$index}.children", $allowedChildren, $next);
        }
    }
}
