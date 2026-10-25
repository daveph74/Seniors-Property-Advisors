<?php

namespace App\Integrations;

use App\Enquiries\FindMyAgentOptions;
use App\Logging\Delivery;
use App\Models\Enquiry;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class SyncId
{
    /** @var array<string, string> */
    public const SOURCES = [
        Enquiry::CONTACT_FORM => 'contact-form',
        Enquiry::FIND_MY_AGENT => 'agent-finder',
    ];

    /** @var array<string, int> */
    public const LIMITS = [
        'property_address' => 500,
        'message' => 5000,
        'suburb' => 255,
        'property_suburb' => 255,
        'state' => 10,
        'property_state' => 10,
        'postcode' => 10,
        'property_postcode' => 10,
        'source' => 100,
        'campaign' => 100,
    ];

    public const RETRY_DELAYS = [200, 1000];

    public function forward(Enquiry $enquiry): void
    {
        $reference = $enquiry->reference();

        $url = config('services.syncid.url');

        if (! is_string($url) || $url === '') {
            Delivery::log()->warning('SyncID skipped an enquiry', [
                'enquiry_id' => $enquiry->id,
                'external_id' => $reference,
                'source' => $enquiry->source,
                'reason' => 'url_not_configured',
            ]);

            return;
        }

        $officeId = config('services.syncid.office_id');

        if (! is_numeric($officeId)) {
            Delivery::log()->error('SyncID skipped an enquiry', [
                'enquiry_id' => $enquiry->id,
                'external_id' => $reference,
                'source' => $enquiry->source,
                'reason' => 'office_id_not_configured',
            ]);

            return;
        }

        $request = $this->request();
        $key = config('services.syncid.key');

        Delivery::log()->info('SyncID sending an enquiry', [
            'enquiry_id' => $enquiry->id,
            'external_id' => $reference,
            'source' => $enquiry->source,
            'url' => $url,
            'office_id' => (int) $officeId,
            'has_api_key' => is_string($key) && $key !== '',
        ]);

        try {
            $response = $request
                ->retry(self::RETRY_DELAYS, when: fn ($e) => $this->worthRetrying($e), throw: false)
                ->post($url, $this->payload($enquiry, (int) $officeId));
        } catch (ConnectionException $e) {
            Delivery::log()->error('SyncID could not receive an enquiry', [
                'enquiry_id' => $enquiry->id,
                'external_id' => $reference,
                'source' => $enquiry->source,
                'url' => $url,
                'message' => $e->getMessage(),
            ]);

            return;
        }

        if ($response->successful()) {
            Delivery::log()->info('SyncID accepted an enquiry', [
                'enquiry_id' => $enquiry->id,
                'external_id' => $reference,
                'source' => $enquiry->source,
                'status' => $response->status(),
            ]);

            return;
        }

        if ($response->status() === 401) {
            Delivery::log()->error('SyncID refused the key or office_id', [
                'enquiry_id' => $enquiry->id,
                'external_id' => $reference,
                'source' => $enquiry->source,
                'url' => $url,
                'status' => $response->status(),
            ]);

            return;
        }

        if ($response->status() === 422) {
            $errors = $response->json('errors');

            Delivery::log()->error('SyncID rejected fields', [
                'enquiry_id' => $enquiry->id,
                'external_id' => $reference,
                'source' => $enquiry->source,
                'url' => $url,
                'status' => $response->status(),
                'fields' => is_array($errors) ? array_keys($errors) : [],
                'body' => $response->body(),
            ]);

            return;
        }

        Delivery::log()->error('SyncID rejected an enquiry', [
            'enquiry_id' => $enquiry->id,
            'external_id' => $reference,
            'source' => $enquiry->source,
            'url' => $url,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);
    }

    public function request(): PendingRequest
    {
        $request = Http::timeout((int) config('services.syncid.timeout', 10))
            ->acceptJson()
            ->asJson();

        $key = config('services.syncid.key');

        return is_string($key) && $key !== ''
            ? $request->withHeaders(['X-Api-Key' => $key])
            : $request;
    }

    private function worthRetrying(mixed $exception): bool
    {
        return $exception instanceof ConnectionException
            || ($exception instanceof RequestException && $exception->response->serverError());
    }

    /** @return array<string, mixed> */
    private function payload(Enquiry $enquiry, int $officeId): array
    {
        $names = $this->names($enquiry->name);
        $wizard = $enquiry->source === Enquiry::FIND_MY_AGENT && is_array($enquiry->details);

        $payload = [
            'office_id' => $officeId,
            'first_name' => $names['first_name'],
            'last_name' => $names['last_name'],
            'email' => $enquiry->email,
            $this->phoneField($enquiry->phone) => $enquiry->phone,
            'suburb' => $enquiry->suburb,
            'message' => $this->message($enquiry->message, $wizard ? $enquiry->details : []),
            'source' => self::SOURCES[$enquiry->source] ?? $enquiry->source,
            'campaign' => $this->campaign($enquiry->page_slug),
            'external_id' => $enquiry->reference(),
        ];

        if ($wizard) {
            $payload = array_merge($payload, $this->locationFields($enquiry->details));
        }

        $payload = array_filter(
            $payload,
            fn ($value) => $value !== null && $value !== '',
        );

        foreach (self::LIMITS as $key => $limit) {
            if (is_string($payload[$key] ?? null)) {
                $payload[$key] = mb_substr($payload[$key], 0, $limit);
            }
        }

        return $payload;
    }

    private function phoneField(?string $phone): string
    {
        $digits = preg_replace('/[\s\-.()]/', '', (string) $phone);

        return preg_match('/^(04|\+614)/', $digits) === 1 ? 'mobile' : 'phone';
    }

    private function message(?string $notes, array $details): ?string
    {
        $answers = array_filter([
            'Property type' => FindMyAgentOptions::label(FindMyAgentOptions::PROPERTY_TYPES, $details['property_type'] ?? null),
            'Timeline' => FindMyAgentOptions::label(FindMyAgentOptions::TIMELINES, $details['timeline'] ?? null),
            'Best time to call' => FindMyAgentOptions::label(FindMyAgentOptions::BEST_TIMES, $details['best_time'] ?? null),
        ], 'filled');

        $lines = implode("\n", array_map(
            fn (string $label, string $value) => "{$label}: {$value}",
            array_keys($answers),
            $answers,
        ));

        return implode("\n\n", array_filter([$notes, $lines], 'filled')) ?: null;
    }

    /** @return array<string, mixed> */
    private function locationFields(array $details): array
    {
        $location = is_array($details['location'] ?? null) ? $details['location'] : [];

        return [
            'state' => $location['state'] ?? null,
            'postcode' => $location['postcode'] ?? null,
            'property_address' => $location['street'] ?? null,
            'property_suburb' => $location['suburb'] ?? null,
            'property_state' => $location['state'] ?? null,
            'property_postcode' => $location['postcode'] ?? null,
        ];
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
