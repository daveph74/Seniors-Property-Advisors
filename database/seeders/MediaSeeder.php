<?php

namespace Database\Seeders;

use App\Content\ImageOptimiser;
use App\Models\Media;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * The pictures the seeded pages point at.
 *
 * They were hotlinked from a stock photography CDN, which meant a site that otherwise makes no
 * third-party request pulled six images off somebody else's host, none of them swappable from the
 * CMS and none of them counted by `MediaController::usage()`.
 *
 * Keys are fixed rather than the `Y/m/<ulid>` an upload mints, because a committed page file has
 * to name the address it wants and a seeder cannot invent the same random id twice. They still
 * satisfy `MediaController::isOurKey()`, so the ordinary `/media/{key}` route serves them and the
 * media library treats them like anything else — including letting an editor replace them.
 *
 * Idempotent on key, like ContentSeeder is on slug. Re-running refreshes the bytes and the row
 * without minting duplicates.
 */
class MediaSeeder extends Seeder
{
    private const PREFIX = '2026/08/';

    /** @var array<string, string> file in resources/media => what an editor sees in the library */
    private const IMAGES = [
        'hero-home.jpg' => 'Home page hero',
        'share-card.jpg' => 'Default sharing image',
        'advisor-meeting.jpg' => 'An advisor showing a couple a tablet',
        'family-paperwork.jpg' => 'Family going through paperwork',
        'home-exterior.jpg' => 'Australian home exterior',
        'rachel.jpg' => 'Client portrait — Rachel',
        'agent-sw.jpg' => 'Illustrated agent SW',
        'agent-jp.jpg' => 'Illustrated agent JP',
        'agent-em.jpg' => 'Illustrated agent EM',
    ];

    public function run(): void
    {
        $disk = Storage::disk('s3');
        $optimiser = new ImageOptimiser;

        foreach (self::IMAGES as $file => $name) {
            $path = resource_path('media/'.$file);

            if (! is_file($path)) {
                $this->command?->warn("Missing {$file} — skipped.");

                continue;
            }

            $key = self::PREFIX.$file;
            $bytes = file_get_contents($path);
            $thumb = null;

            try {
                $disk->put($key, $bytes);
                $thumb = $optimiser->thumbnail($bytes, 'image/jpeg');

                if ($thumb !== null) {
                    $disk->put('thumbs/'.$key, $thumb['bytes']);
                }
            } catch (Throwable $e) {
                /* The row is still written. Without the bytes the picture 404s either way, but the
                   row carries the width and height, and those are what stop a shared link falling
                   back to the small card. Better to be measurably right and visibly missing than
                   missing twice over. */
                $this->command?->warn('Storage unreachable, so the files were not uploaded: '.$e->getMessage());
                $this->command?->line('Is it running?  docker compose up -d  (then seed again)');
            }

            [$width, $height] = $optimiser->measure($bytes);

            Media::updateOrCreate(['key' => $key], [
                'thumb_key' => $thumb !== null ? 'thumbs/'.$key : null,
                'name' => $file,
                'alt' => $name,
                'mime' => 'image/jpeg',
                'size' => strlen($bytes),
                'width' => $width,
                'height' => $height,
                'disk' => 's3',
            ]);
        }
    }

    /** The address a seeded page should point at, so the two cannot drift. */
    public static function url(string $file): string
    {
        return '/media/'.self::PREFIX.$file;
    }
}
