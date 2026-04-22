<?php

use App\Models\Restaurant;
use App\Services\Restaurants\FoursquareProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function fsqProvider(): FoursquareProvider
{
    return new FoursquareProvider(
        baseUrl: 'https://places-api.test',
        apiKey: 'TESTKEY',
        apiVersion: '2025-06-17',
    );
}

it('stableIntId() is deterministic and fits in a signed 63-bit int', function () {
    $id1 = FoursquareProvider::stableIntId('4ee54bf7a69d89905ce34f1b');
    $id2 = FoursquareProvider::stableIntId('4ee54bf7a69d89905ce34f1b');

    expect($id1)->toBe($id2);
    expect($id1)->toBeGreaterThan(0);
    expect($id1)->toBeLessThan(PHP_INT_MAX);
});

it('search() maps the 2025 Foursquare shape to our normalized structure', function () {
    Http::fake([
        'places-api.test/*' => Http::response([
            'results' => [
                [
                    'fsq_place_id' => '4e577535d22d61e8f339cf36',
                    'name' => 'Suteki Sushi',
                    'latitude' => -6.201,
                    'longitude' => 106.808,
                    'categories' => [
                        [
                            'fsq_category_id' => '4bf58dd8d48988d1d2941735',
                            'name' => 'Sushi Restaurant',
                            'icon' => ['prefix' => 'https://ss3.4sqi.net/img/categories_v2/food/sushi_', 'suffix' => '.png'],
                        ],
                        [
                            'fsq_category_id' => '4bf58dd8d48988d111941735',
                            'name' => 'Japanese Restaurant',
                            'icon' => ['prefix' => 'https://ss3.4sqi.net/img/categories_v2/food/japanese_', 'suffix' => '.png'],
                        ],
                    ],
                    'location' => [
                        'address' => 'Pejaten Village',
                        'locality' => 'Jakarta',
                        'formatted_address' => 'Pejaten Village, Jakarta, Jakarta',
                    ],
                ],
            ],
        ], 200),
    ]);

    $result = fsqProvider()->search(['q' => 'sushi', 'count' => 3]);

    expect($result['results_found'])->toBe(1);
    $first = $result['restaurants'][0];
    expect($first['name'])->toBe('Suteki Sushi');
    expect($first['cuisines'])->toBe(['Sushi Restaurant', 'Japanese Restaurant']);
    expect($first['address'])->toBe('Pejaten Village, Jakarta, Jakarta');
    expect($first['location'])->toEqual(['lat' => -6.201, 'lon' => 106.808]);
    expect($first['id'])->toBe(FoursquareProvider::stableIntId('4e577535d22d61e8f339cf36'));
    expect($first['thumb_url'])->toBe('https://ss3.4sqi.net/img/categories_v2/food/sushi_64.png');
    expect($first['fsq_place_id'])->toBe('4e577535d22d61e8f339cf36');
    // Free tier: no ratings, hours, or phone — they come from Premium endpoints.
    expect($first['rating'])->toBeNull();
});

it('search() sends the correct authentication + version headers', function () {
    Http::fake([
        'places-api.test/*' => Http::response(['results' => []], 200),
    ]);

    fsqProvider()->search(['q' => 'pizza']);

    Http::assertSent(function ($request) {
        $auth = $request->header('Authorization')[0] ?? '';
        $ver = $request->header('X-Places-Api-Version')[0] ?? '';

        return $auth === 'Bearer TESTKEY' && $ver === '2025-06-17';
    });
});

it('search() falls back to Jakarta coords when the caller supplies none', function () {
    Http::fake([
        'places-api.test/*' => Http::response(['results' => []], 200),
    ]);

    fsqProvider()->search(['q' => 'sushi']);

    Http::assertSent(function ($request) {
        $ll = $request->data()['ll'] ?? null;

        return is_string($ll) && str_starts_with($ll, '-6.2088');
    });
});

it('search() returns empty on 4xx (e.g. paid endpoint hit while out of credits)', function () {
    Http::fake([
        'places-api.test/*' => Http::response(['message' => 'Your account has no API credits remaining.'], 402),
    ]);

    $result = fsqProvider()->search(['q' => 'sushi']);

    expect($result['results_found'])->toBe(0);
    expect($result['restaurants'])->toBe([]);
});

it('getNearby() uses sort=distance and radius=2000', function () {
    Http::fake([
        'places-api.test/*' => Http::response(['results' => []], 200),
    ]);

    fsqProvider()->getNearby(-6.2, 106.8, 5);

    Http::assertSent(function ($request) {
        $data = $request->data();

        return ($data['sort'] ?? null) === 'distance'
            && (int) ($data['radius'] ?? 0) === 2000
            && (int) ($data['limit'] ?? 0) === 5;
    });
});

it('getRestaurant() serves from the Postgres cache — no HTTP call', function () {
    $fsqPlaceId = '4e577535d22d61e8f339cf36';
    $id = FoursquareProvider::stableIntId($fsqPlaceId);

    Restaurant::create([
        'zomato_id' => $id,
        'name' => 'Suteki Sushi',
        'address' => 'Pejaten Village, Jakarta',
        'rating' => null,
        'cuisines' => ['Sushi Restaurant'],
        'latitude' => -6.201,
        'longitude' => 106.808,
        'raw' => ['fsq_place_id' => $fsqPlaceId],
    ]);

    // If the provider reaches out to Foursquare for details, fail loudly.
    Http::fake(['places-api.test/*' => Http::response('should not be called', 500)]);
    Http::preventStrayRequests();

    $r = fsqProvider()->getRestaurant($id);

    expect($r)->not->toBeNull();
    expect($r['name'])->toBe('Suteki Sushi');
    expect($r['menu_url'])->toBe('https://foursquare.com/v/'.$fsqPlaceId);
    Http::assertNothingSent();
});

it('getRestaurant() returns null when nothing is cached in Postgres', function () {
    Http::fake(['places-api.test/*' => Http::response('should not be called', 500)]);

    expect(fsqProvider()->getRestaurant(99999999))->toBeNull();
});

it('getReviews() returns empty on free tier (Premium endpoint skipped)', function () {
    $result = fsqProvider()->getReviews(123, 0, 5);

    expect($result)->toMatchArray(['total' => 0, 'reviews' => []]);
});

it('getDailyMenu() returns empty (Foursquare has no menu data)', function () {
    expect(fsqProvider()->getDailyMenu(123))->toBe([]);
});
