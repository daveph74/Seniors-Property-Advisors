<?php

namespace App\Console\Commands;

use App\Models\Activity;
use App\Models\Enquiry;
use Illuminate\Console\Command;

/**
 * Enquiries stop being kept eventually.
 *
 * They are the most personal thing this application holds — a name, a telephone number, and somebody's
 * account of what is happening in their life — and until now nothing ever removed one. A record kept
 * after it is any use is a record that can only be lost, subpoenaed or read by somebody it was never
 * meant for, and Australian Privacy Principle 11.2 asks that it be destroyed or de-identified once
 * the purpose it was collected for has passed.
 *
 * Twenty-four months by default, because a seller who enquired two years ago and never sold is a
 * stranger now, not a lead. The number is an option rather than a constant: it is a business decision
 * and the business should be able to change it without a deploy.
 *
 * Reports unless told to act, and run by hand rather than scheduled — the same shape and the same
 * reasoning as `content:purge-deleted`. A cron job that quietly destroys the record of somebody
 * asking for help should be a decision, not a side effect.
 *
 * Deletion goes through the model, so `Activity::note()` records that an enquiry was removed and
 * still does not record whose it was.
 */
class PurgeEnquiries extends Command
{
    protected $signature = 'enquiries:purge {--months=24} {--force : Actually delete, rather than reporting}';

    protected $description = 'Permanently remove enquiries older than the given number of months';

    public function handle(): int
    {
        $months = max(1, (int) $this->option('months'));
        $force = (bool) $this->option('force');
        $cutoff = now()->subMonths($months);

        $enquiries = Enquiry::where('created_at', '<', $cutoff)->orderBy('id')->get();

        foreach ($enquiries as $enquiry) {
            /* The reference rather than the name: this output goes to a terminal, a deploy log and
               whatever scrolls past a colleague, and none of those need to hold somebody's name to
               tell you how many are going. */
            $this->line(($force ? 'Removing ' : 'Would remove ')
                .$enquiry->reference().' — received '.$enquiry->created_at->format('j M Y'));

            if ($force) {
                Activity::note('deleted', 'Enquiry', $enquiry->reference().' (retention)');
                $enquiry->delete();
            }
        }

        $this->info($enquiries->isEmpty()
            ? "No enquiry is older than {$months} months."
            : ($force
                ? "Removed {$enquiries->count()}."
                : "Would remove {$enquiries->count()}. Run again with --force."));

        return self::SUCCESS;
    }
}
