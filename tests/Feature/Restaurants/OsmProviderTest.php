<?php

use App\Services\Restaurants\OsmProvider;
use Illuminate\Support\Facades\Http;

function osmProvider(): OsmProvider
{
    return new OsmProvider(
        nominatimBaseUrl: 'https://nominatim.test',
        overpassBaseUrl: 'https://overpass.test',
        userAgent: 'laravel-culinary-bot/test',
    );
}

it('search() maps Nominatim results into normalized restaurant shape', function () {
    Http::fake([
        'nominatim.test/search*' => Http::response([
            [
                'osm_id' => 12179476430,
                'lat' => '-6.2184887',
                'lon' => '106.8044976',
                'name' => 'Shirato Sushi',
                'display_name' => 'Shirato Sushi, Jalan Lingkar Stadion, Jakarta Pusat, Indonesia',
                'address' => ['road' => 'Jalan Lingkar Stadion', 'city' => 'Jakarta Pusat'],
            ],
            [
                'osm_id' => 99,
                'lat' => '-6.2',
                'lon' => '106.8',
                'name' => 'Sushi Tei',
                'display_name' => 'Sushi Tei, Senayan',
                'address' => ['suburb' => 'Senayan'],
            ],
        ], 200),
    ]);

    $result = osmProvider()->search(['q' => 'sushi', 'count' => 5]);

    expect($result['results_found'])->toBe(2);
    expect($result['count'])->toBe(2);
    expect($result['restaurants'][0])->toMatchArray([
        'id' => 12179476430,
        'name' => 'Shirato Sushi',
        'address' => 'Shirato Sushi, Jalan Lingkar Stadion, Jakarta Pusat, Indonesia',
    ]);
    expect($result['restaurants'][0]['location'])->toEqual(['lat' => -6.2184887, 'lon' => 106.8044976]);
    // Nominatim never exposes ratings / reviews / cuisines for most nodes —
    // the normalized shape should reflect that nulls-out cleanly.
    expect($result['restaurants'][0]['rating'])->toBeNull();
    expect($result['restaurants'][0]['cuisines'])->toBe([]);
});

it('search() appends ", Jakarta" to the q parameter for city scoping', function () {
    Http::fake([
        'nominatim.test/search*' => Http::response([], 200),
    ]);

    osmProvider()->search(['q' => 'pizza', 'count' => 3]);

    Http::assertSent(function ($request) {
        $q = $request->data()['q'] ?? null;

        return is_string($q) && str_contains($q, 'pizza') && str_contains($q, 'Jakarta');
    });
});

it('search() sends the configured User-Agent (Nominatim ToS requirement)', function () {
    Http::fake([
        'nominatim.test/search*' => Http::response([], 200),
    ]);

    osmProvider()->search(['q' => 'burger']);

    Http::assertSent(fn ($request) => $request->header('User-Agent')[0] === 'laravel-culinary-bot/test');
});

it('search() returns an empty result when Nominatim is down', function () {
    Http::fake([
        'nominatim.test/search*' => Http::response('Service Unavailable', 503),
    ]);

    $result = osmProvider()->search(['q' => 'sushi']);

    expect($result['results_found'])->toBe(0);
    expect($result['restaurants'])->toBe([]);
});

it('getRestaurant() maps the Nominatim /lookup response', function () {
    Http::fake([
        'nominatim.test/lookup*' => Http::response([
            [
                'osm_id' => 42,
                'lat' => '-6.2',
                'lon' => '106.8',
                'name' => 'Warung Tekko',
                'display_name' => 'Warung Tekko, Jakarta',
            ],
        ], 200),
    ]);

    $r = osmProvider()->getRestaurant(42);

    expect($r)->not->toBeNull();
    expect($r['id'])->toBe(42);
    expect($r['name'])->toBe('Warung Tekko');
});

it('getRestaurant() returns null on an empty Nominatim response', function () {
    Http::fake([
        'nominatim.test/lookup*' => Http::response([], 200),
    ]);

    expect(osmProvider()->getRestaurant(1))->toBeNull();
});

it('getNearby() parses Overpass elements into our shape', function () {
    Http::fake([
        'overpass.test*' => Http::response([
            'elements' => [
                [
                    'type' => 'node',
                    'id' => 6249714151,
                    'lat' => -6.2116566,
                    'lon' => 106.8440752,
                    'tags' => [
                        'amenity' => 'restaurant',
                        'cuisine' => 'indonesian',
                        'name' => 'Simpang Raya',
                    ],
                ],
                [
                    'type' => 'node',
                    'id' => 6862458085,
                    'lat' => -6.21,
                    'lon' => 106.845,
                    'tags' => [
                        'amenity' => 'restaurant',
                        'name' => 'Sambal Setan',
                        'phone' => '+62 21 1234',
                        'opening_hours' => 'Mo-Su 10:00-22:00',
                        'website' => 'https://sambal.example',
                    ],
                ],
            ],
        ], 200),
    ]);

    $result = osmProvider()->getNearby(-6.2088, 106.8456, 5);

    expect($result['total'])->toBe(2);
    expect($result['restaurants'][0]['name'])->toBe('Simpang Raya');
    expect($result['restaurants'][0]['cuisines'])->toBe(['indonesian']);
    expect($result['restaurants'][1]['phone'])->toBe('+62 21 1234');
    expect($result['restaurants'][1]['hours'])->toBe('Mo-Su 10:00-22:00');
    expect($result['restaurants'][1]['menu_url'])->toBe('https://sambal.example');
});

it('getNearby() splits multi-value OSM cuisine tags on ";"', function () {
    Http::fake([
        'overpass.test*' => Http::response([
            'elements' => [
                [
                    'id' => 1,
                    'lat' => 0,
                    'lon' => 0,
                    'tags' => ['name' => 'Fusion', 'cuisine' => 'pizza;italian;pasta'],
                ],
            ],
        ], 200),
    ]);

    $result = osmProvider()->getNearby(0, 0, 1);

    expect($result['restaurants'][0]['cuisines'])->toBe(['pizza', 'italian', 'pasta']);
});

it('getReviews() returns an empty list (OSM has no reviews)', function () {
    $result = osmProvider()->getReviews(123, 0, 5);

    expect($result)->toMatchArray(['total' => 0, 'count' => 0, 'reviews' => []]);
});

it('getDailyMenu() returns an empty list (OSM has no menus)', function () {
    expect(osmProvider()->getDailyMenu(123))->toBe([]);
});

it('search() with empty q routes to Overpass, not Nominatim', function () {
    // Regression: Nominatim returns zero results for generic category
    // browse like "restaurant, Jakarta". Empty-q must fall back to
    // Overpass QL so a "browse all" UX isn't silently empty.
    Http::fake([
        'overpass.test*' => Http::response([
            'elements' => [
                ['id' => 1, 'lat' => -6.2, 'lon' => 106.8, 'tags' => ['name' => 'Warung', 'amenity' => 'restaurant']],
                ['id' => 2, 'lat' => -6.21, 'lon' => 106.81, 'tags' => ['name' => 'Cafe', 'amenity' => 'restaurant']],
            ],
        ], 200),
        'nominatim.test*' => Http::response([], 200),
    ]);

    $result = osmProvider()->search(['q' => '', 'count' => 5]);

    expect($result['results_found'])->toBe(2);
    expect($result['restaurants'])->toHaveCount(2);
    Http::assertSent(fn ($r) => str_contains($r->url(), 'overpass.test'));
    Http::assertNotSent(fn ($r) => str_contains($r->url(), 'nominatim.test/search'));
});

it('search() with empty q paginates Overpass results by start + count', function () {
    Http::fake([
        'overpass.test*' => Http::response([
            'elements' => array_map(
                fn ($i) => ['id' => $i, 'lat' => -6.2, 'lon' => 106.8, 'tags' => ['name' => "Warung {$i}"]],
                range(1, 5),
            ),
        ], 200),
    ]);

    $result = osmProvider()->search(['q' => '', 'count' => 2, 'start' => 0]);

    expect($result['results_found'])->toBe(5);
    expect($result['count'])->toBe(2);
    expect($result['restaurants'])->toHaveCount(2);
});
