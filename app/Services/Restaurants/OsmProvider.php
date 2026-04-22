<?php

namespace App\Services\Restaurants;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OpenStreetMap-backed restaurant provider.
 *
 * Uses two public endpoints, both free and keyless:
 *  - Nominatim (geocoder) for text search + detail lookups.
 *  - Overpass API for proximity (nearby) queries using Overpass QL.
 *
 * OSM is a POI catalogue, not a review site. There are no ratings, reviews
 * or menus in the dataset, so getReviews / getDailyMenu return empty
 * structures intentionally. Use this provider when a running restaurant
 * data source is needed without any API key (local dev, demo, fallback).
 *
 * Rate limiting: Nominatim public service is fair-use ~1 req/s; Overpass
 * public instance is ~10k queries/day. Both require a real User-Agent
 * identifying the calling application — spoofing a browser UA is an ODbL
 * ToS violation. We expose the UA as a config value so deployments can
 * set it explicitly.
 */
class OsmProvider implements RestaurantProvider
{
    public function __construct(
        private readonly string $nominatimBaseUrl,
        private readonly string $overpassBaseUrl,
        private readonly string $userAgent,
        private readonly string $defaultCity = 'Jakarta',
    ) {}

    private function client(string $baseUrl): PendingRequest
    {
        return Http::withHeaders([
            'User-Agent' => $this->userAgent,
            'Accept' => 'application/json',
        ])
            ->baseUrl($baseUrl)
            ->timeout(15)
            ->retry(1, 250, throw: false);
    }

    // Jakarta city centre — used for the empty-q browse fallback.
    private const JAKARTA_LAT = -6.2088;

    private const JAKARTA_LON = 106.8456;

    /**
     * Text search.
     *
     *  - Named query (e.g. "sushi") → Nominatim /search with ", Jakarta"
     *    appended so the geocoder scopes results to the target city.
     *  - Empty query (browse-all) → fall back to an Overpass QL query for
     *    amenity=restaurant nodes around Jakarta centre. Nominatim does
     *    NOT work as a category-browse engine (it returns zero results
     *    for generic "restaurant, Jakarta"), but Overpass does.
     *
     * @param  array<string,mixed>  $criteria
     * @return array{results_found:int,start:int,count:int,restaurants:array<int,array<string,mixed>>}
     */
    public function search(array $criteria): array
    {
        $q = trim((string) ($criteria['q'] ?? ''));
        $count = max(1, min(20, (int) ($criteria['count'] ?? 10)));
        $start = max(0, (int) ($criteria['start'] ?? 0));

        if ($q === '') {
            // Browse all: Overpass nearby around Jakarta centre. Wide radius
            // so the first page feels populated; results get clipped to the
            // caller's count+start afterwards.
            $nearby = $this->getNearby(self::JAKARTA_LAT, self::JAKARTA_LON, $count + $start);
            $paginated = array_slice($nearby['restaurants'], $start, $count);

            return [
                'results_found' => $nearby['total'],
                'start' => $start,
                'count' => count($paginated),
                'restaurants' => $paginated,
            ];
        }

        $response = $this->client($this->nominatimBaseUrl)->get('/search', [
            'q' => "{$q}, {$this->defaultCity}",
            'format' => 'json',
            'addressdetails' => 1,
            'limit' => $count + $start,
            'countrycodes' => 'id',
        ]);

        if (! $response->successful()) {
            Log::warning('OsmProvider: Nominatim search failed', [
                'status' => $response->status(),
                'q' => $q,
            ]);

            return $this->emptySearch($start);
        }

        /** @var list<array<string,mixed>> $items */
        $items = (array) $response->json();
        $normalized = array_map(fn (array $node): array => $this->normalizeNominatim($node), $items);
        $paginated = array_slice($normalized, $start, $count);

        return [
            'results_found' => count($normalized),
            'start' => $start,
            'count' => count($paginated),
            'restaurants' => $paginated,
        ];
    }

    /**
     * Look up one restaurant by its OSM node id (we store that as our
     * normalized `id` field, so the caller round-trips what search handed
     * them).
     *
     * @return array<string,mixed>|null
     */
    public function getRestaurant(int $id): ?array
    {
        $response = $this->client($this->nominatimBaseUrl)->get('/lookup', [
            'osm_ids' => 'N'.$id,
            'format' => 'json',
            'addressdetails' => 1,
        ]);

        if (! $response->successful()) {
            Log::warning('OsmProvider: Nominatim lookup failed', [
                'status' => $response->status(),
                'osm_id' => $id,
            ]);

            return null;
        }

        /** @var list<array<string,mixed>> $items */
        $items = (array) $response->json();

        if ($items === []) {
            return null;
        }

        return $this->normalizeNominatim($items[0]);
    }

    /**
     * Proximity search via Overpass QL. Returns `amenity=restaurant` nodes
     * within `radius` metres of the point. Radius defaults to 1km; Overpass
     * is happy with anything up to ~5km without timing out.
     *
     * @return array{total:int,restaurants:array<int,array<string,mixed>>}
     */
    public function getNearby(float $lat, float $lon, int $count = 5): array
    {
        $radius = 1000; // metres
        $query = sprintf(
            '[out:json][timeout:15];node(around:%d,%F,%F)["amenity"="restaurant"];out %d;',
            $radius,
            $lat,
            $lon,
            $count,
        );

        $response = $this->client($this->overpassBaseUrl)->asForm()->post('', [
            'data' => $query,
        ]);

        if (! $response->successful()) {
            Log::warning('OsmProvider: Overpass nearby failed', [
                'status' => $response->status(),
                'lat' => $lat,
                'lon' => $lon,
            ]);

            return ['total' => 0, 'restaurants' => []];
        }

        /** @var array{elements?:list<array<string,mixed>>} $body */
        $body = (array) $response->json();
        $elements = $body['elements'] ?? [];

        $normalized = array_map(
            fn (array $node): array => $this->normalizeOverpass($node),
            $elements,
        );

        return [
            'total' => count($normalized),
            'restaurants' => $normalized,
        ];
    }

    /**
     * OSM has no review data. Return an empty payload shaped like the rest
     * of our interface so callers can treat "no reviews" uniformly.
     *
     * @return array{total:int,start:int,count:int,reviews:array<int,array<string,mixed>>}
     */
    public function getReviews(int $restaurantId, int $start = 0, int $count = 5): array
    {
        return [
            'total' => 0,
            'start' => $start,
            'count' => 0,
            'reviews' => [],
        ];
    }

    /**
     * OSM has no menu data.
     *
     * @return list<array{name:string,price:string,description:string|null}>
     */
    public function getDailyMenu(int $restaurantId): array
    {
        return [];
    }

    /**
     * OSM has no "city" concept exposed as a typed endpoint. Return a
     * single-element list for Jakarta (the bot's default scope) so callers
     * that gate on a city id have something to use.
     *
     * @return list<array{id:int,name:string,country_name:string|null}>
     */
    public function getCities(?string $q = null, ?float $lat = null, ?float $lon = null): array
    {
        return [
            ['id' => 74, 'name' => $this->defaultCity, 'country_name' => 'Indonesia'],
        ];
    }

    /**
     * OSM has cuisine tags on individual restaurants but no canonical
     * cuisine-registry endpoint. Return an empty list.
     *
     * @return list<array{id:int,name:string}>
     */
    public function getCuisines(int $cityId): array
    {
        return [];
    }

    /**
     * @param  array<string,mixed>  $node
     * @return array<string,mixed>
     */
    private function normalizeNominatim(array $node): array
    {
        /** @var array<string,mixed> $address */
        $address = $node['address'] ?? [];
        $displayName = (string) ($node['display_name'] ?? '');
        $addressLine = (string) (
            $address['road']
            ?? $address['suburb']
            ?? $address['city']
            ?? $displayName
        );

        return [
            'id' => (int) ($node['osm_id'] ?? 0),
            'name' => (string) ($node['name'] ?? $address['leisure'] ?? $address['amenity'] ?? $displayName),
            'address' => $displayName !== '' ? $displayName : $addressLine,
            'rating' => null,
            'cuisines' => [],
            'location' => [
                'lat' => (float) ($node['lat'] ?? 0),
                'lon' => (float) ($node['lon'] ?? 0),
            ],
            'thumb_url' => null,
            'image_url' => null,
            'phone' => null,
            'hours' => null,
            'menu_url' => null,
        ];
    }

    /**
     * @param  array<string,mixed>  $node
     * @return array<string,mixed>
     */
    private function normalizeOverpass(array $node): array
    {
        /** @var array<string,string> $tags */
        $tags = $node['tags'] ?? [];

        $cuisines = [];
        if (isset($tags['cuisine']) && $tags['cuisine'] !== '') {
            // OSM encodes multi-cuisine as ";"-separated, e.g. "pizza;italian".
            $cuisines = array_values(array_filter(
                array_map('trim', explode(';', (string) $tags['cuisine']))
            ));
        }

        return [
            'id' => (int) ($node['id'] ?? 0),
            'name' => (string) ($tags['name'] ?? 'Unnamed restaurant'),
            'address' => (string) ($tags['addr:full'] ?? $tags['addr:street'] ?? ''),
            'rating' => null,
            'cuisines' => $cuisines,
            'location' => [
                'lat' => (float) ($node['lat'] ?? 0),
                'lon' => (float) ($node['lon'] ?? 0),
            ],
            'thumb_url' => null,
            'image_url' => null,
            'phone' => isset($tags['phone']) ? (string) $tags['phone'] : null,
            'hours' => isset($tags['opening_hours']) ? (string) $tags['opening_hours'] : null,
            'menu_url' => isset($tags['website']) ? (string) $tags['website'] : null,
        ];
    }

    /**
     * @return array{results_found:int,start:int,count:int,restaurants:array<int,array<string,mixed>>}
     */
    private function emptySearch(int $start): array
    {
        return [
            'results_found' => 0,
            'start' => $start,
            'count' => 0,
            'restaurants' => [],
        ];
    }
}
