<?php

namespace App\Services\Restaurants;

use App\Models\Restaurant;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Foursquare Places (2025 v1) restaurant provider.
 *
 * Endpoints used (all on the free "Pro" tier):
 *  - GET /places/search         — text + geo search.
 *  - GET /places/search?ll=&sort=distance — proximity search.
 *
 * Endpoints NOT called (Premium tier, requires paid credits):
 *  - GET /places/{fsq_place_id}            — place details.
 *  - GET /places/{fsq_place_id}/tips       — reviews.
 * Search results already carry the fields we surface (name, categories,
 * address, coordinates), and our RestaurantRepository persists those to
 * Postgres on write-through, so getRestaurant() can fall back to the DB
 * without calling Foursquare again.
 *
 * Foursquare IDs (`fsq_place_id`) are opaque hex strings like
 * "4e577535d22d61e8f339cf36". Our RestaurantProvider interface returns
 * `int`, so we hash the string to a stable 60-bit integer via SHA-1 and
 * keep the original string in the normalized payload's raw provider data
 * for round-trip lookups.
 */
class FoursquareProvider implements RestaurantProvider
{
    // Jakarta city centre — used when the caller doesn't supply coordinates.
    private const DEFAULT_LAT = -6.2088;

    private const DEFAULT_LON = 106.8456;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly string $apiVersion,
    ) {}

    private function client(): PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer '.$this->apiKey,
            'X-Places-Api-Version' => $this->apiVersion,
            'Accept' => 'application/json',
        ])
            ->baseUrl($this->baseUrl)
            ->timeout(10)
            ->retry(1, 250, throw: false);
    }

    /**
     * Hash a Foursquare opaque-hex id into a stable positive 60-bit int
     * suitable for our `int $id` interface. SHA-1 first 15 hex chars = 60
     * bits, fits cleanly under PHP_INT_MAX (2^63 - 1). Collision space ~10^18
     * — effectively zero for our scale.
     */
    public static function stableIntId(string $fsqPlaceId): int
    {
        return (int) hexdec(substr(sha1($fsqPlaceId), 0, 15));
    }

    /**
     * Text search. Scope defaults to Jakarta when no coords are supplied.
     *
     * @param  array<string,mixed>  $criteria
     * @return array{results_found:int,start:int,count:int,restaurants:array<int,array<string,mixed>>}
     */
    public function search(array $criteria): array
    {
        $q = trim((string) ($criteria['q'] ?? ''));
        $count = max(1, min(20, (int) ($criteria['count'] ?? 10)));
        $start = max(0, (int) ($criteria['start'] ?? 0));
        $lat = isset($criteria['lat']) ? (float) $criteria['lat'] : self::DEFAULT_LAT;
        $lon = isset($criteria['lon']) ? (float) $criteria['lon'] : self::DEFAULT_LON;

        $params = [
            'll' => $lat.','.$lon,
            // Bias to the Jakarta metro bounding-circle to keep results local.
            'radius' => 20000,
            'limit' => $count + $start,
        ];

        if ($q !== '') {
            $params['query'] = $q;
        }

        $response = $this->client()->get('/places/search', $params);

        if (! $response->successful()) {
            Log::warning('FoursquareProvider: search failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'criteria' => $criteria,
            ]);

            return $this->emptySearch($start);
        }

        /** @var array{results?:list<array<string,mixed>>} $body */
        $body = (array) $response->json();
        $results = $body['results'] ?? [];
        $normalized = array_map(fn (array $place): array => $this->normalizePlace($place), $results);
        $paginated = array_slice($normalized, $start, $count);

        return [
            'results_found' => count($normalized),
            'start' => $start,
            'count' => count($paginated),
            'restaurants' => $paginated,
        ];
    }

    /**
     * Look up a single restaurant by our internal int id.
     *
     * Place Details is a paid Premium endpoint on Foursquare's pricing, so
     * rather than calling /places/{id}, we resolve via our own DB — the
     * Repository already persists every place we see in a search response.
     * The caller gets the normalized shape they'd otherwise get from search.
     *
     * @return array<string,mixed>|null
     */
    public function getRestaurant(int $id): ?array
    {
        $row = Restaurant::query()->where('zomato_id', $id)->first();

        if ($row === null) {
            return null;
        }

        /** @var array<string,mixed> $raw */
        $raw = is_array($row->raw) ? $row->raw : [];
        $fsqPlaceId = (string) ($raw['fsq_place_id'] ?? '');

        return [
            'id' => $id,
            'name' => (string) $row->name,
            'address' => (string) ($row->address ?? ''),
            'rating' => $row->rating !== null ? (float) $row->rating : null,
            'cuisines' => is_array($row->cuisines) ? $row->cuisines : [],
            'location' => [
                'lat' => (float) ($row->latitude ?? 0),
                'lon' => (float) ($row->longitude ?? 0),
            ],
            'thumb_url' => $row->thumb_url,
            'image_url' => $row->image_url,
            'phone' => $row->phone,
            'hours' => $row->hours,
            'menu_url' => $fsqPlaceId !== ''
                ? 'https://foursquare.com/v/'.$fsqPlaceId
                : null,
        ];
    }

    /**
     * Nearby — /places/search with ll + sort=distance. Free tier.
     *
     * @return array{total:int,restaurants:array<int,array<string,mixed>>}
     */
    public function getNearby(float $lat, float $lon, int $count = 5): array
    {
        $response = $this->client()->get('/places/search', [
            'll' => $lat.','.$lon,
            'radius' => 2000,
            'sort' => 'distance',
            'limit' => max(1, min(20, $count)),
        ]);

        if (! $response->successful()) {
            Log::warning('FoursquareProvider: nearby failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return ['total' => 0, 'restaurants' => []];
        }

        /** @var array{results?:list<array<string,mixed>>} $body */
        $body = (array) $response->json();
        $results = $body['results'] ?? [];

        $normalized = array_map(fn (array $place): array => $this->normalizePlace($place), $results);

        return [
            'total' => count($normalized),
            'restaurants' => $normalized,
        ];
    }

    /**
     * Foursquare Tips (the review-equivalent endpoint) sits behind a paid
     * Premium tier. We return an empty review list rather than incur
     * unexpected charges — this is a deliberate free-tier trade-off.
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
     * Foursquare does not expose structured menu data in any tier.
     *
     * @return list<array{name:string,price:string,description:string|null}>
     */
    public function getDailyMenu(int $restaurantId): array
    {
        return [];
    }

    /**
     * Foursquare's geotagging model is "places in a radius / country", not
     * an enumerated city list. Return a single-element Jakarta entry so
     * callers always get something consistent with the OSM provider.
     *
     * @return list<array{id:int,name:string,country_name:string|null}>
     */
    public function getCities(?string $q = null, ?float $lat = null, ?float $lon = null): array
    {
        return [
            ['id' => 74, 'name' => 'Jakarta', 'country_name' => 'Indonesia'],
        ];
    }

    /**
     * Foursquare has a rich category taxonomy but no "cuisines by city"
     * endpoint. Returning an empty list; the repository doesn't use this
     * method on the user-facing paths we care about.
     *
     * @return list<array{id:int,name:string}>
     */
    public function getCuisines(int $cityId): array
    {
        return [];
    }

    /**
     * @param  array<string,mixed>  $place
     * @return array<string,mixed>
     */
    private function normalizePlace(array $place): array
    {
        $fsqPlaceId = (string) ($place['fsq_place_id'] ?? '');

        /** @var array<string,mixed> $location */
        $location = $place['location'] ?? [];

        /** @var list<array<string,mixed>> $categories */
        $categories = $place['categories'] ?? [];
        $cuisines = [];
        foreach ($categories as $cat) {
            if (isset($cat['name']) && $cat['name'] !== '') {
                $cuisines[] = (string) $cat['name'];
            }
        }

        $firstIcon = $categories[0]['icon'] ?? null;
        $thumbUrl = null;
        if (is_array($firstIcon)
            && isset($firstIcon['prefix'], $firstIcon['suffix'])
            && is_string($firstIcon['prefix'])
            && is_string($firstIcon['suffix'])
        ) {
            $thumbUrl = $firstIcon['prefix'].'64'.$firstIcon['suffix'];
        }

        return [
            'id' => self::stableIntId($fsqPlaceId),
            'name' => (string) ($place['name'] ?? ''),
            'address' => (string) (
                $location['formatted_address']
                ?? $location['address']
                ?? $location['locality']
                ?? ''
            ),
            'rating' => null, // Premium-only field; not populated on free tier.
            'cuisines' => $cuisines,
            'location' => [
                'lat' => isset($place['latitude']) ? (float) $place['latitude'] : 0.0,
                'lon' => isset($place['longitude']) ? (float) $place['longitude'] : 0.0,
            ],
            'thumb_url' => $thumbUrl,
            'image_url' => $thumbUrl,
            'phone' => null,
            'hours' => null,
            'menu_url' => $fsqPlaceId !== ''
                ? 'https://foursquare.com/v/'.$fsqPlaceId
                : null,
            // Round-trip the original string id + link so persistence can
            // rebuild the mapping on getRestaurant() calls later.
            'fsq_place_id' => $fsqPlaceId,
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
