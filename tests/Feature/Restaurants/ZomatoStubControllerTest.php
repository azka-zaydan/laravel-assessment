<?php

use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Restaurant::create([
        'zomato_id' => 16507621,
        'name' => 'Pizzeria Napoli Jakarta',
        'address' => 'Jl. Sudirman No. 10, Jakarta Selatan',
        'rating' => 4.3,
        'cuisines' => ['Italian', 'Pizza'],
        'latitude' => -6.2241,
        'longitude' => 106.8131,
        'phone' => '+62 21 5551234',
        'hours' => '11:00 AM - 11:00 PM',
        'thumb_url' => 'https://example.test/thumb.jpg',
        'image_url' => 'https://example.test/image.jpg',
        'raw' => ['fsq_place_id' => null],
    ]);
});

it('GET /zomato/api/v2.1/restaurant wraps the payload under a "restaurant" key', function () {
    // Regression: the stub previously returned a flat node; Zomato v2.1 contract
    // wraps the detail response under a "restaurant" key. Clients following the
    // spec (including our own ZomatoProvider) read $body.restaurant.name, so a
    // flat shape silently returned null for every field.
    $response = $this->getJson('/zomato/api/v2.1/restaurant?res_id=16507621');

    $response->assertOk()
        ->assertJsonStructure([
            'restaurant' => [
                'R' => ['res_id'],
                'id',
                'name',
                'location' => ['address', 'latitude', 'longitude'],
                'user_rating' => ['aggregate_rating'],
            ],
        ]);

    expect($response->json('restaurant.name'))->toBe('Pizzeria Napoli Jakarta');
    expect($response->json('restaurant.R.res_id'))->toBe(16507621);
    // The old flat shape would have put "name" at the root. Assert it is not there.
    expect($response->json('name'))->toBeNull();
});

it('GET /zomato/api/v2.1/restaurant returns 400 for unknown res_id', function () {
    $response = $this->getJson('/zomato/api/v2.1/restaurant?res_id=999999');

    $response->assertStatus(400)
        ->assertJson(['success' => false]);
});

it('GET /zomato/api/v2.1/search returns Zomato-shaped envelope', function () {
    $response = $this->getJson('/zomato/api/v2.1/search?q=pizza&count=5');

    $response->assertOk()
        ->assertJsonStructure([
            'results_found',
            'results_start',
            'results_shown',
            'restaurants' => [
                '*' => ['restaurant' => ['R' => ['res_id'], 'name', 'location']],
            ],
        ]);

    expect($response->json('results_found'))->toBeGreaterThan(0);
});

it('GET /zomato/api/v2.1/search with no query returns all seeded rows', function () {
    $response = $this->getJson('/zomato/api/v2.1/search');

    $response->assertOk();
    expect($response->json('results_found'))->toBeGreaterThanOrEqual(1);
});

it('GET /zomato/api/v2.1/cities always returns Jakarta', function () {
    $response = $this->getJson('/zomato/api/v2.1/cities');

    $response->assertOk()
        ->assertJsonPath('location_suggestions.0.name', 'Jakarta')
        ->assertJsonPath('location_suggestions.0.id', 74);
});

it('GET /zomato/api/v2.1/cuisines derives unique cuisines from seeded restaurants', function () {
    $response = $this->getJson('/zomato/api/v2.1/cuisines');

    $response->assertOk();
    $cuisineNames = collect($response->json('cuisines'))
        ->pluck('cuisine.cuisine_name')
        ->all();
    // Italian + Pizza were the cuisines on the seeded restaurant — both must appear.
    expect($cuisineNames)->toContain('Italian');
    expect($cuisineNames)->toContain('Pizza');
});
