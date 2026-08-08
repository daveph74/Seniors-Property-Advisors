<?php

namespace App\Content;

use App\Models\Media;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Everything the sharing tags need, worked out once on the server.
 *
 * Open Graph requires an **absolute** URL for og:image. Content stores media as `/media/…`,
 * which is right for an <img> on the page but is silently useless to most crawlers, so it is
 * made absolute here rather than in each page component.
 *
 * The width and height matter too: without them a crawler that has not yet fetched the image
 * often renders the small card instead of the large one, so the first person to share a link
 * gets the worse preview. The media table already knows the dimensions.
 */
class Seo
{
    public static function forSharing(array $seo, ?string $fallbackImage, string $url): array
    {
        $image = trim((string) ($seo['image'] ?? '')) !== '' ? $seo['image'] : $fallbackImage;

        return array_merge($seo, [
            'url' => $url,
            'image' => self::absolute($image),
        ] + self::dimensions($image));
    }

    /**
     * The finished tag values, resolved on the server.
     *
     * They have to exist in the HTML before any JavaScript runs. Google renders a page and would
     * find them either way, but Facebook, LinkedIn, X, WhatsApp, Slack and iMessage do not — they
     * read the document as delivered, so a link shared anywhere but search was arriving with no
     * image, no description, and every page reporting the site title.
     */
    public static function head(array $seo, string $fallbackTitle, string $type = 'website', array $defaults = []): array
    {
        $title = self::formatted(trim((string) ($seo['title'] ?? '')) ?: $fallbackTitle, $defaults);
        $description = trim((string) ($seo['description'] ?? ''))
            ?: (trim((string) ($defaults['description'] ?? '')) ?: null);

        return array_filter([
            'title' => $title,
            'description' => $description,
            'canonical' => trim((string) ($seo['canonical'] ?? '')) ?: ($seo['url'] ?? null),
            'robots' => ($seo['noindex'] ?? false) ? 'noindex, follow' : null,
            'ogType' => $type,
            'ogUrl' => $seo['url'] ?? null,
            'image' => $seo['image'] ?? null,
            'imageWidth' => $seo['imageWidth'] ?? null,
            'imageHeight' => $seo['imageHeight'] ?? null,
            'twitterCard' => ($seo['image'] ?? null) ? 'summary_large_image' : 'summary',
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * The site-wide title pattern, applied to whichever title won.
     *
     * Skipped when the title already contains the site's name — the home page is titled
     * "Agent Finder — Seniors Property Advisors", and blindly appending would deliver
     * "… Advisors | Seniors Property Advisors" to every search result and shared link.
     */
    private static function formatted(string $title, array $defaults): string
    {
        $format = trim((string) ($defaults['titleFormat'] ?? ''));
        $name = trim((string) ($defaults['name'] ?? ''));

        if ($format === '' || $name === '' || Str::contains($title, $name)) {
            return $title;
        }

        return trim(str_replace(['{title}', '{site}'], [$title, $name], $format));
    }

    /**
     * What a search engine needs to treat an article as an article rather than a page of text.
     * Only fields we actually hold are included — a guessed date or an empty author is worse than
     * an absent one, because structured data that disagrees with the page is distrusted.
     */
    public static function articleSchema(array $head, array $article, ?string $publisher = null): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $head['title'] ?? null,
            'description' => $head['description'] ?? null,
            'image' => isset($head['image']) ? [$head['image']] : null,
            'datePublished' => $article['publishedAt'] ?? null,
            'dateModified' => $article['updatedAt'] ?? null,
            'author' => isset($article['author'])
                ? ['@type' => 'Person', 'name' => $article['author']]
                : null,
            /* The website's name, once the settings screen holds one — it was hardcoded here. */
            'publisher' => ['@type' => 'Organization', 'name' => $publisher ?: 'Seniors Property Advisors'],
            'mainEntityOfPage' => isset($head['canonical'])
                ? ['@type' => 'WebPage', '@id' => $head['canonical']]
                : null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * Who the website belongs to, for the home page.
     *
     * Everything here is read from content the client already maintains rather than restated, so
     * changing the footer address changes what search engines are told. A field we do not hold is
     * left out entirely — `array_filter` at the end — because an empty property is a claim that
     * the value is empty rather than unknown.
     */
    public static function organizationSchema(array $globals, array $defaults, array $social = []): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $defaults['name'] ?? null,
            'url' => url('/'),
            'logo' => self::logo($globals, $defaults),
            'telephone' => $globals['phone']['label'] ?? null,
            'address' => self::postalAddress($globals['footer']['address'] ?? []),
            'sameAs' => array_column($social, 'href') ?: null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * Google will not accept an SVG for an organisation's logo, and this codebase already refuses
     * SVG for a sharing image. The site's own logo is an SVG today, so rather than ship something
     * that fails validation, fall back to the default sharing picture — and pick the real logo up
     * automatically the day a raster one is added.
     */
    private static function logo(array $globals, array $defaults): ?string
    {
        $logo = trim((string) ($globals['logo']['src'] ?? ''));

        if ($logo === '' || Str::endsWith(Str::lower($logo), '.svg')) {
            $logo = (string) ($defaults['image'] ?? '');
        }

        return self::absolute($logo);
    }

    /**
     * The footer holds the address as the two lines it is printed on. The street is line one; the
     * rest is only split up when it matches the Australian pattern exactly, since a suburb guessed
     * out of a line that reads differently is worse than an address without one.
     */
    private static function postalAddress(array $lines): ?array
    {
        $street = trim(rtrim(trim((string) ($lines[0] ?? '')), ','));
        $rest = trim((string) ($lines[1] ?? ''));

        if ($street === '' && $rest === '') {
            return null;
        }

        $parts = ['@type' => 'PostalAddress', 'streetAddress' => $street ?: null, 'addressCountry' => 'AU'];

        if (preg_match('/^(.+?)\s+([A-Z]{2,3})\s+(\d{4})$/', $rest, $found) === 1) {
            $parts['addressLocality'] = $found[1];
            $parts['addressRegion'] = $found[2];
            $parts['postalCode'] = $found[3];
        }

        return array_filter($parts, fn ($value) => $value !== null && $value !== '');
    }

    /**
     * No `potentialAction`. A SearchAction tells Google there is a search page to send people to,
     * and this website has none — declaring one that 404s is worse than declaring nothing.
     */
    public static function websiteSchema(array $defaults): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $defaults['name'] ?? null,
            'url' => url('/'),
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * The questions on the page, for any page carrying a `faq-list` block — not just /faqs, since
     * the block is reusable and hardcoding the slug would leave a second FAQ page with nothing.
     *
     * Google penalises an FAQPage describing questions a reader cannot see, so this does not run
     * its own query. It takes the same library the section is handed and repeats the section's
     * own selection: category, then limit, then the hand-written fallback when the library turns
     * up empty. Keep it in step with FaqListSection.jsx.
     */
    public static function faqSchema(array $sections, array $libraryFaqs): ?array
    {
        $questions = [];

        foreach (self::blocksOfType($sections, 'faq-list') as $block) {
            $data = $block['data'] ?? [];
            $limit = (int) ($data['limit'] ?? 0);

            $pulled = array_values(array_filter(
                $libraryFaqs,
                fn ($faq) => empty($data['category']) || ($faq['category'] ?? null) === $data['category'],
            ));

            if ($limit > 0) {
                $pulled = array_slice($pulled, 0, $limit);
            }

            $items = $pulled ?: array_filter(
                $data['items'] ?? [],
                fn ($faq) => is_array($faq) && (filled($faq['question'] ?? null) || filled($faq['answer'] ?? null)),
            );

            foreach ($items as $faq) {
                $question = trim(strip_tags((string) ($faq['question'] ?? '')));
                $answer = trim(strip_tags((string) ($faq['answer'] ?? '')));

                if ($question === '' || $answer === '') {
                    continue;
                }

                $questions[$question] = [
                    '@type' => 'Question',
                    'name' => $question,
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $answer],
                ];
            }
        }

        return $questions === [] ? null : [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_values($questions),
        ];
    }

    /**
     * Home, then this page. Built from the address rather than the menu: the menu is a set of
     * links a client arranges, while the address is the only thing that says where a page
     * actually sits. A segment with no published page behind it is dropped rather than given an
     * invented label.
     */
    public static function breadcrumbSchema(string $url, string $title, array $ancestorTitles = []): ?array
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

        if ($path === '') {
            return null;
        }

        $items = [['name' => 'Home', 'item' => url('/')]];
        $walked = '';

        foreach (explode('/', $path) as $segment) {
            $walked .= '/'.$segment;

            if ($walked === '/'.$path) {
                $items[] = ['name' => $title, 'item' => url($walked)];

                continue;
            }

            if (isset($ancestorTitles[$walked])) {
                $items[] = ['name' => $ancestorTitles[$walked], 'item' => url($walked)];
            }
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn ($item, $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $item['name'],
                'item' => $item['item'],
            ], $items, array_keys($items)),
        ];
    }

    /** Sections nest, so a block can sit inside a section, row or column rather than at the top. */
    private static function blocksOfType(array $sections, string $type): array
    {
        $found = [];

        foreach ($sections as $section) {
            if (! is_array($section)) {
                continue;
            }

            if (($section['type'] ?? null) === $type) {
                $found[] = $section;
            }

            $found = array_merge($found, self::blocksOfType($section['children'] ?? [], $type));
        }

        return $found;
    }

    public static function absolute(?string $image): ?string
    {
        $image = trim((string) $image);

        if ($image === '') {
            return null;
        }

        if (Str::startsWith($image, ['http://', 'https://', '//'])) {
            return $image;
        }

        return url($image);
    }

    /**
     * Only for our own pictures, since the dimensions of somebody else's URL are unknowable —
     * and a wrong width is worse than none. Media library first, then a file shipped with the
     * application: the site's default sharing image lives in public/ rather than the library, and
     * without this it would go out sized-less and crawlers would draw the small card anyway,
     * which is the whole thing the default was set to avoid.
     */
    private static function dimensions(?string $image): array
    {
        $key = self::mediaKey($image);

        if ($key === null) {
            return self::localDimensions($image);
        }

        $media = Media::where('key', $key)->first(['width', 'height']);

        if ($media?->width === null || $media?->height === null) {
            return [];
        }

        return ['imageWidth' => (int) $media->width, 'imageHeight' => (int) $media->height];
    }

    /**
     * A file under public/. The path reaches here from the settings screen, so it is treated as
     * untrusted: `getimagesize()` is a file read, and a path walking out of public/ would turn a
     * sharing-image field into a way to ask whether an arbitrary file exists. Resolved with
     * realpath and checked to still be inside public/ before anything is opened.
     *
     * Remembered because otherwise every page render stats the file and parses its header.
     */
    private static function localDimensions(?string $image): array
    {
        $path = trim((string) $image);

        if (! Str::startsWith($path, '/') || Str::startsWith($path, '//') || Str::contains($path, '..')) {
            return [];
        }

        return Cache::rememberForever('seo:size:'.$path, function () use ($path) {
            $root = realpath(public_path());
            $file = realpath(public_path($path));

            if ($root === false || $file === false || ! Str::startsWith($file, $root) || ! is_file($file)) {
                return [];
            }

            $size = @getimagesize($file);

            return $size === false ? [] : ['imageWidth' => (int) $size[0], 'imageHeight' => (int) $size[1]];
        });
    }

    private static function mediaKey(?string $image): ?string
    {
        $path = parse_url((string) $image, PHP_URL_PATH) ?: '';

        return Str::startsWith($path, '/media/') ? Str::after($path, '/media/') : null;
    }
}
