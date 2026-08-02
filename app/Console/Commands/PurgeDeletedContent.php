<?php

namespace App\Console\Commands;

use App\Http\Controllers\Cms\DeletedContentController;
use Illuminate\Console\Command;

/**
 * "Recently deleted" has to end somewhere, or the bin grows forever and the word stops meaning
 * anything. Run by hand rather than scheduled: nothing here is urgent, and a scheduled job that
 * quietly destroys content is the kind of thing that should be somebody's decision.
 */
class PurgeDeletedContent extends Command
{
    protected $signature = 'content:purge-deleted {--days=90} {--force : Actually delete, rather than reporting}';

    protected $description = 'Permanently remove content deleted longer ago than the given number of days';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);
        $force = (bool) $this->option('force');
        $total = 0;

        foreach (DeletedContentController::KINDS as $kind => $model) {
            $rows = $model::onlyTrashed()->where('deleted_at', '<', $cutoff)->get();

            foreach ($rows as $row) {
                $this->line(($force ? 'Removing ' : 'Would remove ')
                    .$kind.': '.($row->title ?? $row->question ?? $row->name));

                if ($force) {
                    $row->forceDelete();
                }

                $total++;
            }
        }

        $this->info($total === 0
            ? "Nothing has been in the bin longer than {$days} days."
            : ($force ? "Removed {$total}." : "Would remove {$total}. Run again with --force."));

        return self::SUCCESS;
    }
}
