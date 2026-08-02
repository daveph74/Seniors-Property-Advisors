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
        $target = [(int) round($width * $scale), (int) round($height * $scale)];

        $limit = ini_get('memory_limit');
        ini_set('memory_limit', '512M');

        try {
            $source = @imagecreatefromstring($bytes);

            if (! $source instanceof GdImage) {
                return null;
            }

            $resized = imagescale($source, $target[0], $target[1]);
            imagedestroy($source);

            if (! $resized instanceof GdImage) {
                return null;
            }

            $encoded = $this->encode($resized, $mime);
            imagedestroy($resized);
        } finally {
            ini_set('memory_limit', $limit);
        }

        return $encoded === null ? null : [
            'bytes' => $encoded,
            'width' => $target[0],
            'height' => $target[1],
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

    public function tooLarge(string $bytes): bool
    {
        [$width, $height] = $this->measure($bytes);

        return $width !== null && $width * $height > self::MAX_PIXELS;
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
