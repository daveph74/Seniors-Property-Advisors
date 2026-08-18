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

    /**
     * The image addresses in a body that point at somebody else's server.
     *
     * The content policy no longer permits an image from an arbitrary host, so one of these would be
     * stored, published, and then not drawn for any reader — a failure with no error and no witness.
     * The form requests refuse it instead and say what to do, which is the only version of this an
     * editor can act on.
     *
     * A protocol-relative `//host/x` counts, and this site's own absolute address does not: pasting a
     * full URL to something already in the media library is reasonable and works.
     */
    public static function remoteImageSources(?string $html): array
    {
        if ($html === null || trim($html) === '') {
            return [];
        }

        preg_match_all('/<img\b[^>]*?\bsrc\s*=\s*("|\')(.*?)\1/i', $html, $found);

        return array_values(array_unique(array_filter(
            array_map('html_entity_decode', $found[2] ?? []),
            fn (string $src) => self::isRemote($src),
        )));
    }

    /** Whether an address leaves this site. Relative addresses do not. */
    public static function isRemote(?string $value): bool
    {
        $value = trim((string) $value);

        if ($value === '') {
            return false;
        }

        if (str_starts_with($value, '//')) {
            return true;
        }

        $host = parse_url($value, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return false;
        }

        $own = parse_url((string) config('app.url'), PHP_URL_HOST);

        return ! is_string($own) || strcasecmp($host, $own) !== 0;
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
        /* A new tab gets `rel="noopener"` whether the editor knew to ask for it or not. Current
           browsers imply it, but that is their choice to make and not one worth inheriting. */
        $config->set('HTML.TargetNoopener', true);

        /*
         * Images and links must point at this site's media route or an ordinary web address.
         * Anything else — javascript:, data:, file: — is dropped by the URI filter.
         */
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true, 'tel' => true]);
        $config->set('URI.MakeAbsolute', false);

        /*
         * A link may leave this site; an image may not. `img-src` permits this origin and `data:`
         * only, so a remote picture would be stored and then never drawn — and this is the backstop
         * rather than the message: the form requests refuse one and tell the editor to upload it,
         * because a body that arrives here by any other path should still not carry one.
         * `DisableExternalResources` is deliberately not `DisableExternal`, which would take links
         * with it.
         */
        $config->set('URI.DisableExternalResources', true);

        /* Which host is not external. Without it HTMLPurifier treats every absolute address as
           somebody else's, so an image pasted as this site's own full URL was stripped while the
           form request happily allowed it — the two disagreed, and the body came back empty. */
        $config->set('URI.Host', parse_url((string) config('app.url'), PHP_URL_HOST) ?: null);

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
