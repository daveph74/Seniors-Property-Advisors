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
        if ($enquiry->source !== Enquiry::CONTACT_FORM) {
            return;
        }

        $url = config('services.syncid.url');

        if (! is_string($url) || $url === '') {
            return;
        }

        $request = Http::timeout((int) config('services.syncid.timeout', 10))
            ->acceptJson()
            ->asJson();

        $key = config('services.syncid.key');

        if (is_string($key) && $key !== '') {
            $request = $request->withToken($key);
        }

        try {
            $response = $request->post($url, $this->payload($enquiry));
        } catch (ConnectionException $e) {
            Log::error('SyncID could not receive an enquiry', [
                'enquiry_id' => $enquiry->id,
                'message' => $e->getMessage(),
            ]);

            return;
        }

        if ($response->successful()) {
            return;
        }

        Log::warning('SyncID rejected an enquiry', [
            'enquiry_id' => $enquiry->id,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(Enquiry $enquiry): array
    {
        return [
            'name' => $enquiry->name,
            'email' => $enquiry->email,
            'phone' => $enquiry->phone,
            'suburb' => $enquiry->suburb,
            'message' => $enquiry->message,
            'source' => $enquiry->sourceLabel(),
            'page' => $enquiry->page_slug,
            'reference' => $enquiry->reference(),
            'consented' => $enquiry->consented,
        ];
    }
}
