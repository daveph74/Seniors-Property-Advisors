<?php

namespace App\Integrations;

use App\Models\Enquiry;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncId
{
    public function forward(Enquiry $enquiry): void
    {
        $reference = $enquiry->reference();

        if ($enquiry->source !== Enquiry::CONTACT_FORM) {
            Log::info('SyncID skipped an enquiry', [
                'enquiry_id' => $enquiry->id,
                'external_id' => $reference,
                'source' => $enquiry->source,
                'reason' => 'not_contact_form',
            ]);

            return;
        }

        $url = config('services.syncid.url');

        if (! is_string($url) || $url === '') {
            Log::warning('SyncID skipped an enquiry', [
                'enquiry_id' => $enquiry->id,
                'external_id' => $reference,
                'reason' => 'url_not_configured',
            ]);

            return;
        }

        $officeId = config('services.syncid.office_id');

        if (! is_numeric($officeId)) {
            Log::error('SyncID skipped an enquiry', [
                'enquiry_id' => $enquiry->id,
                'external_id' => $reference,
                'reason' => 'office_id_not_configured',
            ]);

            return;
        }

        $request = Http::timeout((int) config('services.syncid.timeout', 10))
            ->acceptJson()
            ->asJson();

        $key = config('services.syncid.key');

        if (is_string($key) && $key !== '') {
            $request = $request->withHeaders(['X-Api-Key' => $key]);
        }

        Log::info('SyncID sending an enquiry', [
            'enquiry_id' => $enquiry->id,
            'external_id' => $reference,
            'url' => $url,
            'office_id' => (int) $officeId,
            'has_api_key' => is_string($key) && $key !== '',
        ]);

        try {
            $response = $request->post($url, $this->payload($enquiry, (int) $officeId));
        } catch (ConnectionException $e) {
            Log::error('SyncID could not receive an enquiry', [
                'enquiry_id' => $enquiry->id,
                'external_id' => $reference,
                'url' => $url,
                'message' => $e->getMessage(),
            ]);

            return;
        }

        if ($response->successful()) {
            Log::info('SyncID accepted an enquiry', [
                'enquiry_id' => $enquiry->id,
                'external_id' => $reference,
                'status' => $response->status(),
            ]);

            return;
        }

        Log::error('SyncID rejected an enquiry', [
            'enquiry_id' => $enquiry->id,
            'external_id' => $reference,
            'url' => $url,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(Enquiry $enquiry, int $officeId): array
    {
        $names = $this->names($enquiry->name);

        return array_filter([
            'office_id' => $officeId,
            'first_name' => $names['first_name'],
            'last_name' => $names['last_name'],
            'email' => $enquiry->email,
            'phone' => $enquiry->phone,
            'suburb' => $enquiry->suburb,
            'message' => $enquiry->message,
            'source' => $enquiry->sourceLabel(),
            'campaign' => $this->campaign($enquiry->page_slug),
            'external_id' => $enquiry->reference(),
        ], fn ($value) => $value !== null && $value !== '');
    }

    /** @return array{first_name: string, last_name: string} */
    private function names(string $name): array
    {
        $name = trim($name);
        $parts = preg_split('/\s+/', $name, 2) ?: [];

        return [
            'first_name' => $parts[0] ?? '',
            'last_name' => $parts[1] ?? '',
        ];
    }

    private function campaign(?string $page): ?string
    {
        if (! is_string($page) || $page === '') {
            return null;
        }

        return ltrim($page, '/');
    }
}
