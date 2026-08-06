<?php

namespace App\Http\Controllers;

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
        $normalised = mb_strtolower(trim($query));

        return Cache::remember(
            'suburbs:autocomplete:'.md5($normalised),
            now()->addDay(),
            function () use ($query, $key, $sessionToken) {
                $response = Http::withHeaders([
                    'X-Goog-Api-Key' => $key,
                    'X-Goog-FieldMask' => 'suggestions.placePrediction.placeId,suggestions.placePrediction.text,suggestions.placePrediction.structuredFormat',
                ])->post(self::AUTOCOMPLETE_URL, array_filter([
                    'input' => $query,
                    'includedPrimaryTypes' => ['locality', 'sublocality'],
                    'includedRegionCodes' => ['au'],
                    'sessionToken' => $sessionToken,
                ]));

                if ($response->failed()) {
                    Log::warning('Places autocomplete failed', [
                        'status' => $response->status(),
                        'body' => $response->json('error.message') ?? $response->body(),
                    ]);

                    return [];
                }

                return collect($response->json('suggestions', []))
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
            }
        );
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
        return Cache::remember(
            'suburbs:details:'.md5($placeId),
            now()->addWeek(),
            function () use ($placeId, $key) {
                $response = Http::withHeaders([
                    'X-Goog-Api-Key' => $key,
                    'X-Goog-FieldMask' => 'addressComponents,location,formattedAddress',
                ])->get(self::DETAILS_URL.$placeId);

                if ($response->failed()) {
                    Log::warning('Places details failed', [
                        'status' => $response->status(),
                        'place_id' => $placeId,
                        'body' => $response->json('error.message') ?? $response->body(),
                    ]);

                    return null;
                }

                $components = collect($response->json('addressComponents', []));

                $component = fn (string $type, string $field) => $components
                    ->first(fn (array $c) => in_array($type, $c['types'] ?? [], true))[$field] ?? null;

                return [
                    'place_id' => $placeId,
                    'suburb' => $component('locality', 'longText')
                        ?? $component('sublocality', 'longText'),
                    'state' => $component('administrative_area_level_1', 'shortText'),
                    'postcode' => $component('postal_code', 'longText'),
                    'lat' => $response->json('location.latitude'),
                    'lng' => $response->json('location.longitude'),
                    'formatted' => $response->json('formattedAddress'),
                ];
            }
        );
    }
}
