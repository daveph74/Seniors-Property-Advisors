<?php

namespace App\Content;

class SectionDiff
{
    public static function between(array $before, array $after): array
    {
        $old = self::flatten($before);
        $new = self::flatten($after);

        $added = [];
        $removed = [];
        $moved = [];
        $edited = [];

        foreach ($new as $id => $node) {
            if (! isset($old[$id])) {
                $added[] = $node['label'];

                continue;
            }

            if ($old[$id]['fingerprint'] !== $node['fingerprint']) {
                $edited[] = $node['label'];
            }

            if ($old[$id]['position'] !== $node['position']) {
                $moved[] = $node['label'];
            }
        }

        foreach ($old as $id => $node) {
            if (! isset($new[$id])) {
                $removed[] = $node['label'];
            }
        }

        return [
            'added' => array_values(array_unique($added)),
            'removed' => array_values(array_unique($removed)),
            'moved' => array_values(array_unique($moved)),
            'edited' => array_values(array_unique($edited)),
            'unchanged' => $added === [] && $removed === [] && $moved === [] && $edited === [],
        ];
    }

    private static function flatten(array $sections, string $path = '', array &$flat = []): array
    {
        foreach ($sections as $index => $section) {
            $id = $section['id'] ?? "{$path}.{$index}";
            $children = $section['children'] ?? [];

            $flat[$id] = [
                'label' => $section['label'] ?? ($section['type'] ?? 'Block'),
                'position' => "{$path}.{$index}",
                'fingerprint' => md5(json_encode([
                    $section['type'] ?? '',
                    $section['label'] ?? '',
                    $section['anchor'] ?? null,
                    $section['active'] ?? true,
                    $section['data'] ?? [],
                ])),
            ];

            self::flatten($children, "{$path}.{$index}", $flat);
        }

        return $flat;
    }
}
