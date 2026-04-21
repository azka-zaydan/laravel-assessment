<?php

namespace App\Services\Restaurants;

use App\Exceptions\Restaurants\ProviderException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class ZomatoProvider implements RestaurantProvider
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $userKey,
    ) {}

    /**
     * Build an authenticated HTTP client pointed at the Zomato API.
     */
    private function client(): PendingRequest
    {
        return Http::withHeaders([
            'user-key' => $this->userKey,
            'Accept' => 'application/json',
        ])
            ->baseUrl($this->baseUrl)
            ->timeout(10)
            ->retry(2, 200);
    }

    /**
     * Assert the response is successful; throw ProviderException otherwise.
     *
     * @throws ProviderException
     */
    private function assertOk(Response $response, string $context): void
    {
        if (! $response->successful()) {
            throw new ProviderException(
                "Zomato [{$context}] HTTP {$response->status()}: {$response->body()}"
            );
        }

        /** @var array<string,mixed> $body */
        $body = $response->json() ?? [];

        if (array_key_exists('success', $body) && $body['success'] === false) {
            throw new ProviderException(
                "Zomato [{$context}] returned success=false: {$response->body()}"
            );
        }
    }

    /**
     * Normalize a raw Zomato restaurant node.
     *
     * @param  array<string,mixed>  $node
     * @return array<string,mixed>
     */
    private function normalizeRestaurant(array $node): array
    {
        /** @var array<string,mixed> $r */
        $r = $node['restaurant'] ?? $node;

        /** @var array<string,mixed> $location */
        $location = $r['location'] ?? [];

        /** @var array<string,mixed> $userRating */
        $userRating = $r['user_rating'] ?? [];

        return [
            'id' => (int) ($r['R']['res_id'] ?? $r['id'] ?? 0),
            'name' => (string) ($r['name'] ?? ''),
            'address' => (string) ($location['address'] ?? ''),
            'rating' => isset($userRating['aggregate_rating'])
                ? (float) $userRating['aggregate_rating']
                : null,
            'cuisines' => array_map('trim', explode(',', (string) ($r['cuisines'] ?? ''))),
            'location' => [
                'lat' => (float) ($location['latitude'] ?? 0),
                'lon' => (float) ($location['longitude'] ?? 0),
            ],
            'thumb_url' => ($r['thumb'] ?? null) ?: null,
            'image_url' => ($r['featured_image'] ?? null) ?: null,
            'phone' => ($r['phone_numbers'] ?? null) ?: null,
            'hours' => ($r['timings'] ?? null) ?: null,
            'menu_url' => ($r['menu_url'] ?? null) ?: null,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function search(array $criteria): array
    {
        $response = $this->client()->get('/search', array_filter([
            'q' => $criteria['q'] ?? null,
            'lat' => $criteria['lat'] ?? null,
            'lon' => $criteria['lon'] ?? null,
            'cuisines' => $criteria['cuisine'] ?? null,
            'count' => $criteria['count'] ?? null,
            'start' => $criteria['start'] ?? null,
        ], fn ($v) => $v !== null));

        $this->assertOk($response, 'search');

        /** @var array<string,mixed> $body */
        $body = $response->json() ?? [];

        /** @var array<mixed> $raw */
        $raw = $body['restaurants'] ?? [];

        $restaurants = array_map(
            fn ($node) => $this->normalizeRestaurant((array) $node),
            $raw
        );

        return [
            'results_found' => (int) ($body['results_found'] ?? count($restaurants)),
            'start' => (int) ($body['results_start'] ?? $criteria['start'] ?? 0),
            'count' => (int) ($body['results_shown'] ?? count($restaurants)),
            'restaurants' => $restaurants,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getRestaurant(int $id): ?array
    {
        $response = $this->client()->get('/restaurant', ['res_id' => $id]);

        if ($response->status() === 404) {
            return null;
        }

        $this->assertOk($response, 'restaurant');

        /** @var array<string,mixed> $body */
        $body = $response->json() ?? [];

        return $this->normalizeRestaurant($body);
    }

    /**
     * {@inheritdoc}
     */
    public function getReviews(int $id, int $start = 0, int $count = 5): array
    {
        $response = $this->client()->get('/reviews', [
            'res_id' => $id,
            'start' => $start,
            'count' => $count,
        ]);

        $this->assertOk($response, 'reviews');

        /** @var array<string,mixed> $body */
        $body = $response->json() ?? [];

        /** @var array<mixed> $raw */
        $raw = $body['user_reviews'] ?? [];

        $reviews = array_map(function (mixed $node) {
            /** @var array<string,mixed> $nodeArr */
            $nodeArr = is_array($node) ? $node : [];
            /** @var array<string,mixed> $item */
            $item = $nodeArr['review'] ?? $nodeArr;
            /** @var array<string,mixed> $user */
            $user = $item['user'] ?? [];

            return [
                'id' => (int) ($item['id'] ?? 0),
                'rating' => (float) ($item['rating'] ?? 0),
                'review_text' => (string) ($item['review_text'] ?? ''),
                'user' => [
                    'name' => (string) ($user['name'] ?? ''),
                    'thumb_url' => ($user['profile_image'] ?? null) ?: null,
                ],
                'created_at' => (string) ($item['review_time_friendly'] ?? ''),
            ];
        }, $raw);

        return [
            'total' => (int) ($body['reviews_count'] ?? count($reviews)),
            'start' => $start,
            'count' => count($reviews),
            'reviews' => $reviews,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getNearby(float $lat, float $lon, int $count = 5): array
    {
        $response = $this->client()->get('/geocode', [
            'lat' => $lat,
            'lon' => $lon,
        ]);

        $this->assertOk($response, 'geocode');

        /** @var array<string,mixed> $body */
        $body = $response->json() ?? [];

        /** @var array<mixed> $raw */
        $raw = array_slice($body['nearby_restaurants'] ?? [], 0, $count);

        $restaurants = array_map(function (mixed $node) {
            /** @var array<string,mixed> $nodeArr */
            $nodeArr = is_array($node) ? $node : [];
            $r = $this->normalizeRestaurant($nodeArr);

            $r['distance_meters'] = isset($nodeArr['restaurant']['distance'])
                ? (int) round((float) $nodeArr['restaurant']['distance'] * 1000)
                : null;

            return $r;
        }, $raw);

        return [
            'total' => count($restaurants),
            'restaurants' => $restaurants,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getDailyMenu(int $id): array
    {
        $response = $this->client()->get('/dailymenu', ['res_id' => $id]);

        $this->assertOk($response, 'dailymenu');

        /** @var array<string,mixed> $body */
        $body = $response->json() ?? [];

        /** @var array<mixed> $dailyMenus */
        $dailyMenus = $body['daily_menus'] ?? [];

        $items = [];
        foreach ($dailyMenus as $menu) {
            /** @var array<string,mixed> $menuArr */
            $menuArr = is_array($menu) ? ($menu['daily_menu'] ?? $menu) : [];
            /** @var array<mixed> $dishes */
            $dishes = $menuArr['dishes'] ?? [];
            foreach ($dishes as $dish) {
                /** @var array<string,mixed> $dishArr */
                $dishArr = is_array($dish) ? ($dish['dish'] ?? $dish) : [];
                $items[] = [
                    'name' => (string) ($dishArr['name'] ?? ''),
                    'price' => (string) ($dishArr['price'] ?? ''),
                    'description' => ($dishArr['description'] ?? null) ?: null,
                ];
            }
        }

        return $items;
    }

    /**
     * {@inheritdoc}
     */
    public function getCities(?string $q = null, ?float $lat = null, ?float $lon = null): array
    {
        $params = array_filter([
            'q' => $q,
            'lat' => $lat,
            'lon' => $lon,
        ], fn ($v) => $v !== null);

        $response = $this->client()->get('/cities', $params);
        $this->assertOk($response, 'cities');

        /** @var array<string,mixed> $body */
        $body = $response->json() ?? [];

        /** @var array<mixed> $raw */
        $raw = $body['location_suggestions'] ?? [];

        return array_map(function (mixed $city) {
            /** @var array<string,mixed> $c */
            $c = is_array($city) ? $city : [];

            return [
                'id' => (int) ($c['id'] ?? 0),
                'name' => (string) ($c['name'] ?? ''),
                'country_name' => ($c['country_name'] ?? null) ?: null,
            ];
        }, $raw);
    }

    /**
     * {@inheritdoc}
     */
    public function getCuisines(int $cityId): array
    {
        $response = $this->client()->get('/cuisines', ['city_id' => $cityId]);
        $this->assertOk($response, 'cuisines');

        /** @var array<string,mixed> $body */
        $body = $response->json() ?? [];

        /** @var array<mixed> $raw */
        $raw = $body['cuisines'] ?? [];

        return array_map(function (mixed $item) {
            /** @var array<string,mixed> $itemArr */
            $itemArr = is_array($item) ? ($item['cuisine'] ?? $item) : [];

            return [
                'id' => (int) ($itemArr['cuisine_id'] ?? $itemArr['id'] ?? 0),
                'name' => (string) ($itemArr['cuisine_name'] ?? $itemArr['name'] ?? ''),
            ];
        }, $raw);
    }
}
