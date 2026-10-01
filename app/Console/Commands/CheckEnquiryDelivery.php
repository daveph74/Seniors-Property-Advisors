<?php

namespace App\Console\Commands;

use App\Content\Site;
use App\Integrations\SyncId;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Where an enquiry goes after it is saved, checked from the server itself.
 *
 * Both deliveries fail open — the visitor is thanked and the reason lands in the log — so on a server
 * the only symptom is a CRM or an inbox that stays empty. This reads the configuration the running
 * application actually has (a cached config ignores a freshly edited `.env`), and with `--send` makes
 * one real SyncID request and sends one real email, printing whatever came back.
 */
class CheckEnquiryDelivery extends Command
{
    protected $signature = 'enquiries:check-delivery {--send : Post a test lead to SyncID and email the notification list}';

    protected $description = 'Report, and optionally test, how enquiries reach SyncID and the team inbox';

    private int $problems = 0;

    public function handle(): int
    {
        if (app()->configurationIsCached()) {
            $this->warn('Configuration is cached: edits to .env do nothing until `php artisan config:cache` runs again.');
            $this->newLine();
        }

        $this->syncId();
        $this->newLine();
        $this->mail();

        $this->newLine();
        $this->problems === 0
            ? $this->info('Nothing wrong found.')
            : $this->error("{$this->problems} problem(s) found.");

        return $this->problems === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function syncId(): void
    {
        $this->line('<options=bold>SyncID</>');

        $url = (string) config('services.syncid.url');
        $officeId = config('services.syncid.office_id');
        $key = (string) config('services.syncid.key');

        $this->line('  URL        '.($url ?: '(blank — nothing is sent)'));
        $this->line('  Office id  '.(filled($officeId) ? $officeId : '(blank)'));
        $this->line('  API key    '.($key === '' ? '(blank)' : 'set, '.strlen($key).' characters'));

        if ($url === '') {
            $this->problem('SYNCID_API_URL is blank.');

            return;
        }

        if (! str_ends_with(rtrim($url, '/'), '/api/website-lead')) {
            $this->problem('SYNCID_API_URL should be https://{tenant}.syncid.com.au/api/website-lead — the site root answers with a login page.');
        }

        if (! is_numeric($officeId)) {
            $this->problem('SYNCID_OFFICE_ID must be a number.');
        }

        if ($key === '') {
            $this->problem('SYNCID_API_KEY is blank; SyncID will answer 401.');
        }

        if (! $this->option('send') || ! is_numeric($officeId)) {
            return;
        }

        $payload = ['office_id' => (int) $officeId, 'first_name' => 'Test', 'email' => 'test@example.com'];

        try {
            $response = app(SyncId::class)->request()->post($url, $payload);
        } catch (Throwable $e) {
            $this->problem('Could not reach SyncID: '.$e->getMessage());

            return;
        }

        $this->line('  Test lead  HTTP '.$response->status().' '.$response->body());

        if (! $response->successful()) {
            $this->problem(match ($response->status()) {
                401, 403 => 'SyncID refused the key. Check SYNCID_API_KEY against BDM settings, and that it belongs to this office.',
                404 => 'SyncID has no such address. Check the tenant in SYNCID_API_URL.',
                419 => 'That URL is the SyncID app, not the webhook. It must end in /api/website-lead.',
                422 => 'SyncID rejected the fields. The body above says which.',
                default => 'SyncID did not accept the test lead.',
            });
        }
    }

    private function mail(): void
    {
        $this->line('<options=bold>Notification email</>');

        $mailer = (string) config('mail.default');
        $recipients = Site::enquiryRecipients();
        $queue = (string) config('queue.default');

        $this->line('  Mailer      '.$mailer);
        $this->line('  From        '.config('mail.from.address'));
        $this->line('  Recipients  '.($recipients === [] ? '(none — set them in Settings → Notifications)' : implode(', ', $recipients)));
        $this->line('  Queue       '.$queue);

        if ($recipients === []) {
            $this->problem('No recipients are listed, so no email is sent.');
        }

        if (in_array($mailer, ['log', 'array'], true)) {
            $this->problem("MAIL_MAILER is {$mailer}: emails are written to the log, not sent.");
        }

        if ($mailer === 'ses_cross_account') {
            $this->line('  SES region  '.(config('mail.mailers.ses_cross_account.region') ?: '(blank)'));
            $this->line('  SES role    '.(config('mail.mailers.ses_cross_account.role_arn') ?: '(blank)'));
        }

        if ($queue !== 'sync') {
            $pending = DB::table('jobs')->count();
            $failed = DB::table('failed_jobs')->count();

            $this->line("  Jobs        {$pending} waiting, {$failed} failed");

            if ($pending > 0) {
                $this->problem('Jobs are waiting: no queue worker is running (`php artisan queue:work`).');
            }
        }

        if (! $this->option('send') || $recipients === []) {
            return;
        }

        try {
            Mail::raw(
                'This is a test from '.config('app.url').'. Enquiry notifications will arrive like this one.',
                fn ($message) => $message->to($recipients)->subject('Test: enquiry notifications'),
            );
        } catch (Throwable $e) {
            $cause = $e->getPrevious() ? ' ('.$e->getPrevious()->getMessage().')' : '';
            $this->problem('Sending failed: '.$e->getMessage().$cause);

            return;
        }

        $this->line('  Test email  sent to '.count($recipients).' address(es)');
    }

    private function problem(string $message): void
    {
        $this->problems++;
        $this->line("  <fg=red>✗</> {$message}");
    }
}
