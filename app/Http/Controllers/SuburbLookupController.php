<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Proxies Google Places (New) lookups for the Find My Agent modal so the API
 * key never reaches the browser.
 *
 * Two modes:
 *   ?q=mosm        → suburb predictions for the autocomplete list
 *   ?place_id=…    → resolved detail for the suburb the visitor picked
 *
 * Google failures always degrade to an empty-but-successful payload. The field
 * falls back to free text, so a Places outage can never block the form.
 */
class SuburbLookupController extends Controller
{
    private const AUTOCOMPLETE_URL = 'https://places.googleapis.com/v1/places:autocomplete';

    private const DETAILS_URL = 'https://places.googleapis.com/v1/places/';

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required_without:place_id', 'string', 'min:2', 'max:100'],
            'place_id' => ['required_without:q', 'string', 'max:255'],
            'session' => ['nullable', 'string', 'max:64'],
        ]);

        $key = config('services.google.places_key');

        if (blank($key)) {
            return response()->json(
                isset($validated['place_id']) ? ['place' => null] : ['suggestions' => []]
            );
        }

        return isset($validated['place_id'])
            ? response()->json(['place' => $this->details($validated['place_id'], $key)])
            : response()->json(['suggestions' => $this->suggestions(
                $validated['q'],
                $key,
                $validated['session'] ?? null
            )]);
    }

    /**
     * Suburb predictions, cached for a day — suburb names don't move, and an
     * autocomplete endpoint bills per keystroke without a cache in front.
     */
    private function suggestions(string $query, string $key, ?string $sessionToken): array
    {
        $cacheKey = 'suburbs:autocomplete:'.md5(mb_strtolower(trim($query)));
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $response = $this->call(
            fn () => Http::withHeaders([
                'X-Goog-Api-Key' => $key,
                'X-Goog-FieldMask' => 'suggestions.placePrediction.placeId,suggestions.placePrediction.text,suggestions.placePrediction.structuredFormat',
            ])->timeout(4)->connectTimeout(2)->post(self::AUTOCOMPLETE_URL, array_filter([
                'input' => $query,
                'includedPrimaryTypes' => ['locality', 'sublocality'],
                'includedRegionCodes' => ['au'],
                'sessionToken' => $sessionToken,
            ])),
            'autocomplete',
        );

        if ($response === null) {
            return [];
        }

        $suggestions = collect($response->json('suggestions', []))
            ->pluck('placePrediction')
            ->filter()
            ->map(fn (array $p) => [
                'id' => $p['placeId'] ?? null,
                'label' => data_get($p, 'structuredFormat.mainText.text') ?? data_get($p, 'text.text'),
                'secondary' => data_get($p, 'structuredFormat.secondaryText.text'),
                'description' => data_get($p, 'text.text'),
            ])
            ->filter(fn (array $s) => filled($s['id']) && filled($s['label']))
            ->values()
            ->all();

        Cache::put($cacheKey, $suggestions, now()->addDay());

        return $suggestions;
    }

    /**
     * One call to Places, returning null for anything that did not come back usable.
     *
     * `failed()` alone only covers a reply Google actually sent. A connection that never
     * completes — a timeout, a DNS or TLS failure — throws instead, and that escaped as a 500
     * from an endpoint whose whole purpose is to degrade quietly. It is also the likelier of the
     * two in production.
     *
     * The timeouts matter as much: without them a slow reply from Google holds a PHP worker for
     * as long as it likes, on an endpoint a reader hits on every keystroke.
     */
    private function call(callable $request, string $what): ?Response
    {
        try {
            $response = $request();
        } catch (ConnectionException $e) {
            Log::warning("Places {$what} unreachable", ['error' => $e->getMessage()]);

            return null;
        }

        if ($response->failed()) {
            Log::warning("Places {$what} failed", [
                'status' => $response->status(),
                'body' => $response->json('error.message') ?? $response->body(),
            ]);

            return null;
        }

        return $response;
    }

    /**
     * Resolve the picked suburb. A `locality` prediction carries no coordinates
     * or postcode, hence the second call.
     *
     * Note: Australian localities frequently come back with no postal_code
     * component at all, so `postcode` is best-effort and often null. The
     * coordinates are always present and are the dependable matching signal.
     */
    private function details(string $placeId, string $key): ?array
    {
        $cacheKey = 'suburbs:details:'.md5($placeId);
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $response = $this->call(
            fn () => Http::withHeaders([
                'X-Goog-Api-Key' => $key,
                'X-Goog-FieldMask' => 'addressComponents,location,formattedAddress',
            ])->timeout(4)->connectTimeout(2)->get(self::DETAILS_URL.$placeId),
            'details',
        );

        if ($response === null) {
            return null;
        }

        $components = collect($response->json('addressComponents', []));

        $component = fn (string $type, string $field) => $components
            ->first(fn (array $c) => in_array($type, $c['types'] ?? [], true))[$field] ?? null;

        $place = [
            'place_id' => $placeId,
            'suburb' => $component('locality', 'longText')
                ?? $component('sublocality', 'longText'),
            'state' => $component('administrative_area_level_1', 'shortText'),
            'postcode' => $component('postal_code', 'longText'),
            'lat' => $response->json('location.latitude'),
            'lng' => $response->json('location.longitude'),
            'formatted' => $response->json('formattedAddress'),
        ];

        Cache::put($cacheKey, $place, now()->addWeek());

        return $place;
    }
}
