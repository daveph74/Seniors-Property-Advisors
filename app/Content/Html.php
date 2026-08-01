<?php

namespace App\Content;

use HTMLPurifier;
use HTMLPurifier_Config;
use Illuminate\Support\Str;

/**
 * The only gate between what an editor types and what a reader receives. Article bodies are
 * HTML now that the editor is a what-you-see one, so they are purified on the way in and the
 * clean result is what gets stored — a body in the database is already safe to print.
 *
 * The allowlist is scope §5's editor list and nothing more: headings, paragraphs, bold and
 * italic, both list types, links, images, quotes and tables. §17 excludes editing raw HTML,
 * so there is no reason to permit anything beyond it.
 */
class Html
{
    private const ALLOWED = 'h2,h3,h4,p,br,hr,strong,b,em,i,u,s,ul,ol,li,blockquote,'
        .'a[href|title|target|rel],img[src|alt|width|height],'
        .'table,thead,tbody,tfoot,tr,th[colspan|rowspan],td[colspan|rowspan],code,pre';

    public static function clean(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        return trim(self::purifier()->purify($html));
    }

    public static function isEmpty(?string $html): bool
    {
        return trim(strip_tags((string) $html, '<img>')) === ''
            && ! Str::contains((string) $html, '<img');
    }

    /**
     * Plain text for cards and meta descriptions when an article has no summary of its own.
     */
    public static function excerpt(?string $html, int $characters = 180): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $html)) ?? '');

        return Str::limit(html_entity_decode($text, ENT_QUOTES | ENT_HTML5), $characters);
    }

    private static function purifier(): HTMLPurifier
    {
        static $purifier = null;

        if ($purifier !== null) {
            return $purifier;
        }

        $config = HTMLPurifier_Config::createDefault();

        $config->set('HTML.Allowed', self::ALLOWED);
        $config->set('AutoFormat.RemoveEmpty', true);
        $config->set('AutoFormat.AutoParagraph', false);
        $config->set('HTML.Nofollow', false);
        $config->set('Attr.AllowedFrameTargets', ['_blank']);

        /*
         * Images and links must point at this site's media route or an ordinary web address.
         * Anything else — javascript:, data:, file: — is dropped by the URI filter.
         */
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true, 'tel' => true]);
        $config->set('URI.MakeAbsolute', false);

        /*
         * HTMLPurifier writes a definition cache to disk. Without a writable path it warns on
         * every call, so it gets one inside storage, created if the deploy has not been through
         * `storage:link` or a fresh clone.
         */
        $cache = storage_path('framework/cache/htmlpurifier');

        if (! is_dir($cache)) {
            @mkdir($cache, 0o775, true);
        }

        $config->set('Cache.SerializerPath', is_writable($cache) ? $cache : null);
        $config->set('Cache.DefinitionImpl', is_writable($cache) ? 'Serializer' : null);

        return $purifier = new HTMLPurifier($config);
    }
}
