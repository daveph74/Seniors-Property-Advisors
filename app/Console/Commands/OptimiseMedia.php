<?php

namespace App\Console\Commands;

use App\Content\ImageOptimiser;
use App\Http\Controllers\Cms\MediaController;
use App\Models\Media;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Brings images uploaded before any of this existed up to the same standard: shrinks anything over
 * the size cap and gives everything the small copy the CMS browses with.
 *
 * Run by hand. Re-running is safe — an image already within the cap keeps its exact bytes, and a
 * small copy is simply rewritten.
 */
class OptimiseMedia extends Command
{
    protected $signature = 'media:optimise {--dry-run : Report what would change and touch nothing}';

    protected $description = 'Shrink oversized library images and build the small copies the CMS uses';

    public function handle(): int
    {
        $optimiser = new ImageOptimiser;
        $disk = Storage::disk('s3');
        $dry = (bool) $this->option('dry-run');
        $saved = 0;

        foreach (Media::where('mime', 'like', 'image/%')->where('mime', '<>', 'image/svg+xml')->get() as $medium) {
            try {
                if (! $disk->exists($medium->key)) {
                    $this->warn($medium->name.' — the file is missing, skipped');

                    continue;
                }

                $bytes = (string) $disk->get($medium->key);
                $was = strlen($bytes);
                $smaller = $optimiser->optimise($bytes, $medium->mime);
                $note = [];

                if ($smaller !== null) {
                    $note[] = $this->size($was).' → '.$this->size(strlen($smaller['bytes']));
                    $saved += $was - strlen($smaller['bytes']);

                    if (! $dry) {
                        $disk->put($medium->key, $smaller['bytes']);
                        $medium->fill([
                            'size' => strlen($smaller['bytes']),
                            'width' => $smaller['width'],
                            'height' => $smaller['height'],
                        ]);
                    }

                    $bytes = $smaller['bytes'];
                }

                if ($medium->thumb_key === null || ! $disk->exists((string) $medium->thumb_key)) {
                    $note[] = 'small copy added';

                    if (! $dry) {
                        $medium->thumb_key = app(MediaController::class)
                            ->makeThumb($bytes, $medium->mime, $medium->key);
                    }
                }

                if ($note === []) {
                    continue;
                }

                if (! $dry) {
                    $medium->save();
                }

                $this->line($medium->name.' — '.implode(', ', $note));
            } catch (Throwable $e) {
                $this->error($medium->name.' — '.$e->getMessage());
            }
        }

        $this->info(($dry ? 'Would save ' : 'Saved ').$this->size(max(0, $saved)).' in originals.');

        return self::SUCCESS;
    }

    private function size(int $bytes): string
    {
        return $bytes >= 1048576
            ? round($bytes / 1048576, 1).' MB'
            : max(1, (int) round($bytes / 1024)).' KB';
    }
}
