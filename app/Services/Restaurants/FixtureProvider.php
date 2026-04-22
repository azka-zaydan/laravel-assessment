<?php

namespace App\Services\Restaurants;

use Illuminate\Support\Facades\Log;

/**
 * Reads Zomato-shaped responses from on-disk fixtures under
 * database/fixtures/zomato/*.json. Used by the test suite (Http::fake
 * would also work but this is simpler for the feature tests that thread
 * through the whole service+repo layer). Not used in production —
 * production routes through ZomatoProvider → the in-app HTTP stub at
 * /zomato/api/v2.1/* (see ZomatoStubController). Both implementations
 * satisfy the same RestaurantProvider contract.
 */
class FixtureProvider implements RestaurantProvider
{
    /**
     * @var array<string,array<mixed>>
     */
    private array $fixtureCache = [];

    /**
     * Load and decode a fixture file (cached in memory).
     *
     * @return array<mixed>
     */
    private function fixture(string $filename): array
    {
        if (isset($this->fixtureCache[$filename])) {
            return $this->fixtureCache[$filename];
        }

        $path = base_path('database/fixtures/zomato/'.$filename);

        if (! file_exists($path)) {
            Log::debug("FixtureProvider: fixture not found [{$filename}] — returning empty array");

            return $this->fixtureCache[$filename] = [];
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            return $this->fixtureCache[$filename] = [];
        }

        /** @var array<mixed>|null $decoded */
        $decoded = json_decode($raw, true);

        return $this->fixtureCache[$filename] = is_array($decoded) ? $decoded : [];
    }

    /**
     * Normalize a raw restaurant node (Zomato envelope) into internal shape.
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
            'address' => (string) ($location['address'] ?? $r['location']['address'] ?? ''),
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
        $data = $this->fixture('search.json');

        /** @var array<mixed> $raw */
        $raw = isset($data['restaurants']) ? (array) $data['restaurants'] : $data;
        $all = array_map(fn ($node) => $this->normalizeRestaurant((array) $node), $raw);

        $start = (int) ($criteria['start'] ?? 0);
        $count = (int) ($criteria['count'] ?? 20);

        // Simple query filter when q is provided. Match name, address, or
        // any cuisine tag — users search by cuisine at least as often as by
        // restaurant name ("sushi", "pizza", "burger" are all cuisines).
        if (! empty($criteria['q'])) {
            $q = mb_strtolower((string) $criteria['q']);
            $all = array_values(array_filter(
                $all,
                function ($r) use ($q): bool {
                    if (str_contains(mb_strtolower($r['name']), $q)) {
                        return true;
                    }
                    if (str_contains(mb_strtolower($r['address']), $q)) {
                        return true;
                    }
                    foreach ($r['cuisines'] as $cuisine) {
                        if (str_contains(mb_strtolower((string) $cuisine), $q)) {
                            return true;
                        }
                    }

                    return false;
                }
            ));
        }

        $total = count($all);
        $paginated = array_slice($all, $start, $count);

        return [
            'results_found' => $total,
            'start' => $start,
            'count' => count($paginated),
            'restaurants' => $paginated,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getRestaurant(int $id): ?array
    {
        $data = $this->fixture('restaurant_'.$id.'.json');

        if (empty($data)) {
            // Fallback: try to find in search fixture
            $search = $this->fixture('search.json');
            /** @var array<mixed> $raw */
            $raw = $search['restaurants'] ?? [];
            foreach ($raw as $node) {
                $n = $this->normalizeRestaurant((array) $node);
                if ($n['id'] === $id) {
                    return $n;
                }
            }

            return null;
        }

        return $this->normalizeRestaurant($data);
    }

    /**
     * {@inheritdoc}
     */
    public function getReviews(int $id, int $start = 0, int $count = 5): array
    {
        $data = $this->fixture('reviews_'.$id.'.json');

        if (empty($data)) {
            $data = $this->fixture('reviews.json');
        }

        /** @var array<mixed> $raw */
        $raw = isset($data['user_reviews']) ? (array) $data['user_reviews'] : $data;

        $normalized = array_map(function (mixed $node) {
            /** @var array<string,mixed> $item */
            $item = is_array($node) ? ($node['review'] ?? $node) : [];
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

        $total = count($normalized);
        $paginated = array_slice($normalized, $start, $count);

        return [
            'total' => $total,
            'start' => $start,
            'count' => count($paginated),
            'reviews' => $paginated,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getNearby(float $lat, float $lon, int $count = 5): array
    {
        $data = $this->fixture('geocode.json');

        /** @var array<mixed> $raw */
        $raw = isset($data['nearby_restaurants'])
            ? (array) $data['nearby_restaurants']
            : (isset($data['restaurants']) ? (array) $data['restaurants'] : $data);
        $raw = array_slice($raw, 0, $count);

        $normalized = array_map(function (mixed $node) {
            /** @var array<string,mixed> $nodeArr */
            $nodeArr = is_array($node) ? $node : [];
            $r = $this->normalizeRestaurant($nodeArr);

            $r['distance_meters'] = isset($nodeArr['restaurant']['distance'])
                ? (int) round((float) $nodeArr['restaurant']['distance'] * 1000)
                : null;

            return $r;
        }, $raw);

        return [
            'total' => count($normalized),
            'restaurants' => array_values($normalized),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getDailyMenu(int $id): array
    {
        $data = $this->fixture('dailymenu_'.$id.'.json');

        if (empty($data)) {
            $data = $this->fixture('dailymenu.json');
        }

        /** @var array<mixed> $dailyMenus */
        $dailyMenus = $data['daily_menus'] ?? [];

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
        $data = $this->fixture('cities.json');
        /** @var array<mixed> $raw */
        $raw = isset($data['location_suggestions']) ? (array) $data['location_suggestions'] : $data;

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
        $data = $this->fixture('cuisines.json');
        /** @var array<mixed> $raw */
        $raw = isset($data['cuisines']) ? (array) $data['cuisines'] : $data;

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
