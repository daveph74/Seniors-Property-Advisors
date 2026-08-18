<?php

namespace App\Content;

use GdImage;

/**
 * Shrinks and re-encodes an uploaded image for delivery, in place.
 *
 * One file per image, deliberately. Generating a set of widths would serve phones better, but the
 * stored key is what every reference to an image is matched on — page trees, drafts, revisions,
 * articles, testimonials, site chrome — so a second file means a second identity to keep track of.
 * The format is kept for the same reason: converting to WebP would change the extension, and the
 * extension is part of the key.
 */
class ImageOptimiser
{
    public const MAX_EDGE = 2400;

    public const QUALITY = 82;

    /**
     * Above this the decoded bitmap is too big to hold. GD needs width × height × 4 bytes for the
     * source alone, and the editor is better told to resize than handed a blank page.
     */
    public const MAX_PIXELS = 40000000;

    private const READERS = [
        'image/jpeg' => 'imagecreatefromstring',
        'image/png' => 'imagecreatefromstring',
        'image/webp' => 'imagecreatefromstring',
        'image/gif' => 'imagecreatefromstring',
    ];

    /**
     * The bytes as they should be stored, or null when they are already fine as they are —
     * re-encoding a small image only loses quality for nothing.
     *
     * @return array{bytes: string, width: int, height: int}|null
     */
    public function optimise(string $bytes, string $mime): ?array
    {
        if (! isset(self::READERS[$mime])) {
            return null;
        }

        [$width, $height] = $this->measure($bytes);

        if ($width === null || max($width, $height) <= self::MAX_EDGE) {
            return null;
        }

        $scale = self::MAX_EDGE / max($width, $height);

        return $this->rescale($bytes, $mime, (int) round($width * $scale), (int) round($height * $scale));
    }

    private function rescale(string $bytes, string $mime, int $width, int $height): ?array
    {
        $limit = ini_get('memory_limit');
        ini_set('memory_limit', '512M');

        try {
            $source = @imagecreatefromstring($bytes);

            if (! $source instanceof GdImage) {
                return null;
            }

            $resized = imagescale($source, max(1, $width), max(1, $height));
            imagedestroy($source);

            if (! $resized instanceof GdImage) {
                return null;
            }

            $encoded = $this->encode($resized, $mime);
            imagedestroy($resized);
        } finally {
            $this->restore($limit);
        }

        return $encoded === null ? null : [
            'bytes' => $encoded,
            'width' => $width,
            'height' => $height,
        ];
    }

    /**
     * What the bytes actually are, rather than what the upload claimed. Returns nulls for anything
     * that is not an image GD recognises, which is how a mislabelled file is caught.
     *
     * @return array{0: int|null, 1: int|null, 2: string|null}
     */
    public function measure(string $bytes): array
    {
        $read = @getimagesizefromstring($bytes);

        if ($read === false || ($read[0] ?? 0) < 1 || ($read[1] ?? 0) < 1) {
            return [null, null, null];
        }

        return [(int) $read[0], (int) $read[1], $read['mime'] ?? null];
    }

    /**
     * A small copy for the CMS to browse with. The library grid and its preview were loading the
     * full-size original — four megabytes to fill a 270px box — which is why an image took a moment
     * to appear when you clicked it.
     *
     * This one is a separate object rather than a resize of the original, because the original is
     * what the website serves and what every reference points at.
     *
     * @return array{bytes: string, width: int, height: int}|null
     */
    public function thumbnail(string $bytes, string $mime, int $edge = 480): ?array
    {
        if (! isset(self::READERS[$mime])) {
            return null;
        }

        [$width, $height] = $this->measure($bytes);

        if ($width === null) {
            return null;
        }

        $scale = min(1, $edge / max($width, $height));

        return $this->rescale($bytes, $mime, (int) round($width * $scale), (int) round($height * $scale));
    }

    public function tooLarge(string $bytes): bool
    {
        [$width, $height] = $this->measure($bytes);

        return $width !== null && $width * $height > self::MAX_PIXELS;
    }

    /**
     * Putting the limit back is conditional, because PHP refuses to lower it below what is already
     * allocated and raises an error doing so — which would fail the request on exactly the large
     * image this exists to shrink. When the decoded bitmap has not been reclaimed yet, the raised
     * limit simply stands for the rest of a request that is about to end.
     */
    private function restore(string|false $limit): void
    {
        if ($limit === false || trim($limit) === '' || (int) $limit === -1) {
            return;
        }

        $units = ['k' => 1024, 'm' => 1048576, 'g' => 1073741824];
        $suffix = strtolower(substr(trim($limit), -1));
        $bytes = (int) $limit * ($units[$suffix] ?? 1);

        if (memory_get_usage(true) < $bytes) {
            ini_set('memory_limit', $limit);
        }
    }

    private function encode(GdImage $image, string $mime): ?string
    {
        /* Without this a transparent PNG comes back with a black background. */
        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($image, false);
            imagesavealpha($image, true);
        }

        ob_start();

        $written = match ($mime) {
            'image/jpeg' => imagejpeg($image, null, self::QUALITY),
            'image/png' => imagepng($image, null, 6),
            'image/webp' => imagewebp($image, null, self::QUALITY),
            'image/gif' => imagegif($image),
            default => false,
        };

        $bytes = (string) ob_get_clean();

        return $written && $bytes !== '' ? $bytes : null;
    }
}
