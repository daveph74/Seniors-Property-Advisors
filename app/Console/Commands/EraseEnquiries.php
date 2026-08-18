<?php

namespace App\Console\Commands;

use App\Models\Activity;
use App\Models\Enquiry;
use Illuminate\Console\Command;

/**
 * Everything this application holds about one person, removed because they asked.
 *
 * Somebody who sent an enquiry can ask for it back out again, and the answer cannot be "an
 * administrator will open the inbox and hope they find them all" — a person who wrote in three times
 * is three rows, and the one that gets missed is the one that matters.
 *
 * Matched on the email address, because that is what a person can quote in the request. Reports what
 * it found and needs `--force` to act, so nobody erases the wrong Janet by mistyping an address.
 *
 * The address is echoed back here, unlike `enquiries:purge`, and deliberately: whoever runs this has
 * been given it by the person themselves, and confirming which address is about to be erased is the
 * whole point of the report.
 */
class EraseEnquiries extends Command
{
    protected $signature = 'enquiries:erase {email} {--force : Actually delete, rather than reporting}';

    protected $description = 'Remove every enquiry sent from one email address, on request';

    public function handle(): int
    {
        $email = trim((string) $this->argument('email'));
        $force = (bool) $this->option('force');

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Give the email address the person asked about.');

            return self::FAILURE;
        }

        $enquiries = Enquiry::whereRaw('lower(email) = ?', [mb_strtolower($email)])->orderBy('id')->get();

        if ($enquiries->isEmpty()) {
            $this->info("Nothing here was sent from {$email}.");

            return self::SUCCESS;
        }

        foreach ($enquiries as $enquiry) {
            $this->line(($force ? 'Erasing ' : 'Would erase ')
                .$enquiry->reference().' — received '.$enquiry->created_at->format('j M Y'));

            if ($force) {
                /* Same as any other deletion: that one went is recorded, whose it was is not. The
                   audit log has no delete path of its own, so an erasure that wrote the name into it
                   would not be an erasure. */
                Activity::note('deleted', 'Enquiry', $enquiry->reference().' (erasure request)');
                $enquiry->delete();
            }
        }

        $this->info($force
            ? "Erased {$enquiries->count()} for {$email}."
            : "Would erase {$enquiries->count()} for {$email}. Run again with --force.");

        return self::SUCCESS;
    }
}
