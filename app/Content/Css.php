<?php

namespace App\Content;

class Css
{
    public const BLOCK_LIMIT = 8000;

    public const SITE_LIMIT = 20000;

    private const REFUSED = [
        '<' => 'the "<" character',
        '@import' => '@import',
        'expression(' => 'expression()',
        'javascript:' => '"javascript:" addresses',
        'behavior:' => 'the behavior property',
        '-moz-binding' => '-moz-binding',
    ];

    public static function clean(?string $css): ?string
    {
        $css = trim(str_replace(["\r\n", "\r"], "\n", (string) $css));

        return $css === '' ? null : $css;
    }

    /**
     * @return list<string>
     */
    public static function problems(?string $css, int $limit = self::BLOCK_LIMIT): array
    {
        $css = (string) $css;
        $problems = [];

        if (mb_strlen($css) > $limit) {
            $problems[] = "more than {$limit} characters";
        }

        $folded = mb_strtolower(preg_replace('/\s+/', '', $css));

        foreach (self::REFUSED as $needle => $label) {
            if (str_contains($folded, $needle)) {
                $problems[] = $label;
            }
        }

        if (preg_match('/\\\\[0-9a-f]/i', $css) === 1) {
            $problems[] = 'backslash escapes';
        }

        return $problems;
    }

    public static function safe(?string $css, int $limit = self::BLOCK_LIMIT): ?string
    {
        $css = self::clean($css);

        return $css !== null && self::problems($css, $limit) === [] ? $css : null;
    }
}
