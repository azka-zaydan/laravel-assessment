<?php

beforeEach(function () {
    config(['services.restaurants.provider' => 'mock']);
});

it('returns 5 nearby restaurants for valid lat/lon', function () {
    actAsConfirmedUser();

    $response = $this->getJson('/api/restaurants/nearby?lat=-6.2088&lon=106.8456');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id', 'name', 'address', 'rating', 'cuisines',
                    'location', 'distance_meters', 'thumb_url',
                ],
            ],
            'meta' => ['total'],
        ]);

    expect($response->json('data'))->toHaveCount(5);
});

it('returns 422 when lat is missing', function () {
    actAsConfirmedUser();

    $this->getJson('/api/restaurants/nearby?lon=106.8456')
        ->assertStatus(422);
});

it('returns 422 when lon is missing', function () {
    actAsConfirmedUser();

    $this->getJson('/api/restaurants/nearby?lat=-6.2088')
        ->assertStatus(422);
});

it('returns 422 when both lat and lon are missing', function () {
    actAsConfirmedUser();

    $this->getJson('/api/restaurants/nearby')
        ->assertStatus(422);
});

it('distance_meters is numeric for all results', function () {
    actAsConfirmedUser();

    $response = $this->getJson('/api/restaurants/nearby?lat=-6.2088&lon=106.8456');
    $response->assertStatus(200);

    foreach ($response->json('data') as $restaurant) {
        if ($restaurant['distance_meters'] !== null) {
            expect($restaurant['distance_meters'])->toBeNumeric();
        }
    }
});

it('nearby restaurants are sorted by distance_meters ascending', function () {
    actAsConfirmedUser();

    $response = $this->getJson('/api/restaurants/nearby?lat=-6.2088&lon=106.8456');
    $response->assertStatus(200);

    $distances = collect($response->json('data'))
        ->pluck('distance_meters')
        ->filter(fn ($d) => $d !== null)
        ->values();

    if ($distances->count() > 1) {
        $sorted = $distances->sort()->values();
        expect($distances->toArray())->toBe($sorted->toArray());
    } else {
        expect(true)->toBeTrue();
    }
});

it('returns 401 when unauthenticated', function () {
    $this->getJson('/api/restaurants/nearby?lat=-6.2088&lon=106.8456')
        ->assertStatus(401);
});
