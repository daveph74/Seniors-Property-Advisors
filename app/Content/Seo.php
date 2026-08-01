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
