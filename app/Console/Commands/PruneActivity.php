<?php

namespace App\Console\Commands;

use App\Models\Activity;
use Illuminate\Console\Command;

/**
 * The audit log stops growing forever.
 *
 * §13 asks that key actions be recorded and that the record identify who made them, and it does —
 * `by_name` is kept as text beside `by_id` precisely so an entry stays readable after the account is
 * gone. Which is also the reason this exists: without an end date, a former employee's name lives in
 * this table indefinitely, and the table is the one thing here with no delete path of its own.
 *
 * Twenty-four months by default, which is far longer than anybody scrolls and long enough to answer
 * "who changed this page, and when". Reports first, needs `--force`, run by hand: the same rule as
 * every other command here that destroys something.
 */
class PruneActivity extends Command
{
    protected $signature = 'activity:prune {--months=24} {--force : Actually delete, rather than reporting}';

    protected $description = 'Remove audit-log entries older than the given number of months';

    public function handle(): int
    {
        $months = max(1, (int) $this->option('months'));
        $force = (bool) $this->option('force');
        $cutoff = now()->subMonths($months);

        $query = Activity::where('created_at', '<', $cutoff);
        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info("No activity is older than {$months} months.");

            return self::SUCCESS;
        }

        $oldest = (clone $query)->oldest('created_at')->value('created_at');

        $this->line(($force ? 'Removing ' : 'Would remove ')."{$total} entries, the oldest from "
            .$oldest?->format('j M Y'));

        if ($force) {
            /* Deleted in one statement rather than model by model: there is no observer on this table
               and nothing to cascade, so a row-at-a-time loop would only be slower. */
            $query->delete();
        }

        $this->info($force ? "Removed {$total}." : 'Run again with --force.');

        return self::SUCCESS;
    }
}
