<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SuburbLookupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.google.places_key', 'test-key');
    }

    private function autocompletePayload(): array
    {
        return [
            'suggestions' => [
                [
                    'placePrediction' => [
                        'placeId' => 'place-mosman',
                        'text' => ['text' => 'Mosman NSW, Australia'],
                        'structuredFormat' => [
                            'mainText' => ['text' => 'Mosman'],
                            'secondaryText' => ['text' => 'NSW, Australia'],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function test_it_maps_places_predictions_to_suggestions(): void
    {
        Http::fake([
            'places.googleapis.com/v1/places:autocomplete' => Http::response($this->autocompletePayload()),
        ]);

        $this->getJson('/api/suburbs?q=mosm')
            ->assertOk()
            ->assertExactJson([
                'suggestions' => [
                    [
                        'id' => 'place-mosman',
                        'label' => 'Mosman',
                        'secondary' => 'NSW, Australia',
                        'description' => 'Mosman NSW, Australia',
                    ],
                ],
            ]);
    }

    public function test_it_restricts_the_lookup_to_australian_localities(): void
    {
        Http::fake([
            'places.googleapis.com/*' => Http::response($this->autocompletePayload()),
        ]);

        $this->getJson('/api/suburbs?q=mosm&session=abc-123')->assertOk();

        Http::assertSent(function ($request) {
            return $request['includedRegionCodes'] === ['au']
                && $request['includedPrimaryTypes'] === ['locality', 'sublocality']
                && $request['sessionToken'] === 'abc-123'
                && $request->hasHeader('X-Goog-Api-Key', 'test-key');
        });
    }

    public function test_it_caches_repeated_queries(): void
    {
        Http::fake([
            'places.googleapis.com/*' => Http::response($this->autocompletePayload()),
        ]);

        $this->getJson('/api/suburbs?q=mosm')->assertOk();
        // Different casing and padding must hit the same cache entry.
        $this->getJson('/api/suburbs?'.http_build_query(['q' => 'MOSM ']))->assertOk();

        Http::assertSentCount(1);
    }

    public function test_it_resolves_place_details(): void
    {
        Http::fake([
            'places.googleapis.com/v1/places/place-mosman*' => Http::response([
                'formattedAddress' => 'Mosman NSW 2088, Australia',
                'location' => ['latitude' => -33.8269, 'longitude' => 151.2437],
                'addressComponents' => [
                    ['types' => ['locality', 'political'], 'longText' => 'Mosman', 'shortText' => 'Mosman'],
                    ['types' => ['administrative_area_level_1'], 'longText' => 'New South Wales', 'shortText' => 'NSW'],
                    ['types' => ['postal_code'], 'longText' => '2088', 'shortText' => '2088'],
                ],
            ]),
        ]);

        $this->getJson('/api/suburbs?place_id=place-mosman')
            ->assertOk()
            ->assertJsonPath('place.suburb', 'Mosman')
            ->assertJsonPath('place.state', 'NSW')
            ->assertJsonPath('place.postcode', '2088')
            ->assertJsonPath('place.lat', -33.8269)
            ->assertJsonPath('place.lng', 151.2437);
    }

    public function test_a_locality_without_a_postcode_still_resolves(): void
    {
        Http::fake([
            'places.googleapis.com/v1/places/*' => Http::response([
                'formattedAddress' => 'Mosman NSW, Australia',
                'location' => ['latitude' => -33.8269, 'longitude' => 151.2437],
                'addressComponents' => [
                    ['types' => ['locality'], 'longText' => 'Mosman', 'shortText' => 'Mosman'],
                    ['types' => ['administrative_area_level_1'], 'longText' => 'New South Wales', 'shortText' => 'NSW'],
                ],
            ]),
        ]);

        $this->getJson('/api/suburbs?place_id=place-mosman')
            ->assertOk()
            ->assertJsonPath('place.suburb', 'Mosman')
            ->assertJsonPath('place.postcode', null);
    }

    public function test_it_rejects_a_query_that_is_too_short(): void
    {
        Http::fake();

        $this->getJson('/api/suburbs?q=m')->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_it_requires_a_query_or_a_place_id(): void
    {
        Http::fake();

        $this->getJson('/api/suburbs')->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_it_returns_no_suggestions_when_the_api_key_is_missing(): void
    {
        config()->set('services.google.places_key', null);
        Http::fake();

        $this->getJson('/api/suburbs?q=mosm')
            ->assertOk()
            ->assertExactJson(['suggestions' => []]);

        Http::assertNothingSent();
    }

    public function test_a_google_failure_degrades_to_an_empty_result(): void
    {
        Http::fake([
            'places.googleapis.com/*' => Http::response(['error' => ['message' => 'boom']], 500),
        ]);

        $this->getJson('/api/suburbs?q=mosm')
            ->assertOk()
            ->assertExactJson(['suggestions' => []]);
    }

    public function test_a_google_details_failure_degrades_to_a_null_place(): void
    {
        Http::fake([
            'places.googleapis.com/*' => Http::response(['error' => ['message' => 'boom']], 500),
        ]);

        $this->getJson('/api/suburbs?place_id=place-mosman')
            ->assertOk()
            ->assertExactJson(['place' => null]);
    }

    /**
     * Not reaching Google at all is a different failure from Google answering badly, and the one
     * more likely in production — a timeout, a DNS wobble, a TLS handshake that will not complete.
     * It throws rather than returning a response, and it used to come back as a 500 from the one
     * endpoint that is supposed to fail quietly.
     */
    public function test_being_unable_to_reach_google_degrades_rather_than_erroring(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: timed out'));

        $this->getJson('/api/suburbs?q=mosm')
            ->assertOk()
            ->assertExactJson(['suggestions' => []]);

        $this->getJson('/api/suburbs?place_id=place-mosman')
            ->assertOk()
            ->assertExactJson(['place' => null]);
    }

    /**
     * A blip must not be remembered. The results are cached for a day, so caching the empty
     * answer as though it were one would take that suburb out of the autocomplete until tomorrow.
     *
     * One stub that fails then succeeds — a second `Http::fake()` appends a stub rather than
     * replacing the first, so the failure would keep winning.
     */
    public function test_a_failure_is_not_cached(): void
    {
        $calls = 0;

        Http::fake(function () use (&$calls) {
            $calls++;

            if ($calls === 1) {
                throw new ConnectionException('down');
            }

            return Http::response(['suggestions' => [[
                'placePrediction' => [
                    'placeId' => 'place-mosman',
                    'text' => ['text' => 'Mosman NSW, Australia'],
                    'structuredFormat' => [
                        'mainText' => ['text' => 'Mosman'],
                        'secondaryText' => ['text' => 'NSW, Australia'],
                    ],
                ],
            ]]]);
        });

        $this->getJson('/api/suburbs?q=mosm')->assertExactJson(['suggestions' => []]);

        $this->getJson('/api/suburbs?q=mosm')
            ->assertOk()
            ->assertJsonPath('suggestions.0.label', 'Mosman');

        $this->assertSame(2, $calls, 'the second request should have reached Google, not a cached failure');
    }
}
