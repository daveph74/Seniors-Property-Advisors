<?php

namespace App\Content;

use App\Models\Media;
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
    public static function head(array $seo, string $fallbackTitle, string $type = 'website'): array
    {
        $title = trim((string) ($seo['title'] ?? '')) ?: $fallbackTitle;
        $description = trim((string) ($seo['description'] ?? '')) ?: null;

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
     * What a search engine needs to treat an article as an article rather than a page of text.
     * Only fields we actually hold are included — a guessed date or an empty author is worse than
     * an absent one, because structured data that disagrees with the page is distrusted.
     */
    public static function articleSchema(array $head, array $article): array
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
            'publisher' => ['@type' => 'Organization', 'name' => 'Seniors Property Advisors'],
            'mainEntityOfPage' => isset($head['canonical'])
                ? ['@type' => 'WebPage', '@id' => $head['canonical']]
                : null,
        ], fn ($value) => $value !== null && $value !== '');
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
     * Only for our own media, since the dimensions of somebody else's URL are unknowable —
     * and a wrong width is worse than none.
     */
    private static function dimensions(?string $image): array
    {
        $key = self::mediaKey($image);

        if ($key === null) {
            return [];
        }

        $media = Media::where('key', $key)->first(['width', 'height']);

        if ($media?->width === null || $media?->height === null) {
            return [];
        }

        return ['imageWidth' => (int) $media->width, 'imageHeight' => (int) $media->height];
    }

    private static function mediaKey(?string $image): ?string
    {
        $path = parse_url((string) $image, PHP_URL_PATH) ?: '';

        return Str::startsWith($path, '/media/') ? Str::after($path, '/media/') : null;
    }
}
