<?php

namespace App\Content;

use App\Auth\Permissions;
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
        $this->checkIdsAreUnique($validator, $items, $root);
        $this->checkCustomCss($validator, $items, $root);
    }

    /**
     * @return list<string> every custom CSS value already stored where this save may land
     */
    protected function storedCustomCss(): array
    {
        return [];
    }

    protected function mayWriteCustomCss(): bool
    {
        return Permissions::allows($this->user(), 'styles.custom');
    }

    private function checkCustomCss(Validator $validator, array $items, string $root): void
    {
        $stored = null;

        $walk = function (array $tree) use (&$walk, &$stored, $validator, $root): void {
            foreach ($tree as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $css = $item['data']['customCss'] ?? null;

                if ($css !== null && $css !== '') {
                    if (! is_string($css)) {
                        $validator->errors()->add($root, 'Custom CSS has to be text.');

                        return;
                    }

                    $problems = Css::problems(Css::clean($css) ?? '');

                    if ($problems !== []) {
                        $validator->errors()->add($root, 'Custom CSS may not contain '.$problems[0].'.');

                        return;
                    }

                    if (! $this->mayWriteCustomCss()) {
                        $stored ??= $this->storedCustomCss();

                        if (! in_array(Css::clean($css), $stored, true)) {
                            $validator->errors()->add($root, 'Only a super administrator can add or change custom CSS.');

                            return;
                        }
                    }
                }

                $walk(is_array($item['children'] ?? null) ? $item['children'] : []);
            }
        };

        $walk($items);
    }

    /**
     * @return list<string>
     */
    protected function customCssIn(array $tree): array
    {
        $found = [];

        $walk = function (array $items) use (&$walk, &$found): void {
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $raw = $item['data']['customCss'] ?? null;
                $css = is_string($raw) ? Css::clean($raw) : null;

                if ($css !== null) {
                    $found[] = $css;
                }

                $walk(is_array($item['children'] ?? null) ? $item['children'] : []);
            }
        };

        $walk($tree);

        return $found;
    }

    private function checkIdsAreUnique(Validator $validator, array $items, string $root): void
    {
        $seen = [];

        $walk = function (array $tree) use (&$walk, &$seen): void {
            foreach ($tree as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $id = $item['id'] ?? null;

                if (is_string($id)) {
                    $seen[$id] = ($seen[$id] ?? 0) + 1;
                }

                $walk(is_array($item['children'] ?? null) ? $item['children'] : []);
            }
        };

        $walk($items);

        foreach ($seen as $id => $count) {
            if ($count > 1) {
                $validator->errors()->add($root, "Two blocks share the id \"{$id}\". Reload the builder and save again.");

                return;
            }
        }
    }

    protected function sanitiseTree(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->isBlock($item) ? $this->sanitiseBlock($item) : $this->sanitiseTree($item);
            } elseif (is_string($item)) {
                $value[$key] = strip_tags($item);
            }
        }

        return $value;
    }

    private function isBlock(array $node): bool
    {
        return is_string($node['type'] ?? null) && is_array($node['data'] ?? null);
    }

    private function sanitiseBlock(array $block): array
    {
        $html = [];

        foreach (PageContentStore::HTML_FIELDS[$block['type']] ?? [] as $field) {
            if (is_string($block['data'][$field] ?? null)) {
                $html[$field] = $block['data'][$field];
            }
        }

        $css = is_string($block['data']['customCss'] ?? null) ? $block['data']['customCss'] : null;

        $block = $this->sanitiseTree($block);

        foreach ($html as $field => $raw) {
            $block['data'][$field] = str_contains($raw, '<') ? Html::cleanInline($raw) : $raw;
        }

        if ($css !== null) {
            $clean = Css::safe($css);

            if ($clean === null) {
                unset($block['data']['customCss']);
            } else {
                $block['data']['customCss'] = $clean;
            }
        }

        return $block;
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
