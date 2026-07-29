<?php

namespace Tests\Feature;

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
}
