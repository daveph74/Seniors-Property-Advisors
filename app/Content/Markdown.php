<?php

namespace App\Content;

use Illuminate\Support\Str;

/**
 * The only place article bodies become HTML. Bodies are stored as the Markdown the
 * editor typed, never as HTML, and `html_input: strip` is what makes rendering safe —
 * CommonMark's own default is `allow`, and GitHub-flavoured Markdown's DisallowedRawHtml
 * only neutralises a handful of tags. Anything that renders a body without coming
 * through here loses that guarantee.
 */
class Markdown
{
    private const OPTIONS = [
        'html_input' => 'strip',
        'allow_unsafe_links' => false,
    ];

    /**
     * Matches what a paste from a web page or an email client would bring with it.
     * Bodies are checked against this on save so the editor is told, rather than
     * having their text silently rewritten.
     */
    public const UNSAFE = '/<\s*(script|iframe|object|embed|form|style|link|meta)\b|\son[a-z]+\s*=|javascript\s*:/i';

    public static function toHtml(?string $markdown): string
    {
        if ($markdown === null || trim($markdown) === '') {
            return '';
        }

        return trim(Str::markdown($markdown, self::OPTIONS));
    }

    public static function holdsUnsafeHtml(?string $markdown): bool
    {
        return $markdown !== null && preg_match(self::UNSAFE, $markdown) === 1;
    }

    /**
     * A summary fallback for cards when an article has none of its own.
     */
    public static function excerpt(?string $markdown, int $characters = 180): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags(self::toHtml($markdown))) ?? '');

        return Str::limit($text, $characters);
    }
}
