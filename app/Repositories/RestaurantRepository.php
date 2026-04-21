<?php

namespace App\Repositories;

use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\Review;
use App\Services\Restaurants\RestaurantProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class RestaurantRepository
{
    // Cache TTLs
    private const TTL_RESTAURANT = 3600;      // 1 hour

    private const TTL_SEARCH = 300;       // 5 minutes

    private const TTL_NEARBY = 900;       // 15 minutes

    private const TTL_REVIEWS = 1800;      // 30 minutes

    private const TTL_MENU = 86400;     // 24 hours (static-ish)

    public function __construct(
        private readonly RestaurantProvider $provider,
    ) {}

    // -------------------------------------------------------------------------
    // Restaurant detail
    // -------------------------------------------------------------------------

    /**
     * Return a normalized restaurant array, using Redis → Postgres → provider fallback.
     *
     * @return array<string,mixed>|null
     */
    public function findOrFetchRestaurant(int $id): ?array
    {
        $cacheKey = 'zomato:restaurant:'.$id;

        /** @var array<string,mixed>|null $cached */
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // Try Postgres first
        $model = Restaurant::where('zomato_id', $id)->first();
        if ($model !== null) {
            $normalized = $this->modelToArray($model);
            Cache::put($cacheKey, $normalized, self::TTL_RESTAURANT);

            return $normalized;
        }

        // Hit the provider
        $data = $this->provider->getRestaurant($id);
        if ($data === null) {
            return null;
        }

        $this->upsertRestaurant($data);
        Cache::put($cacheKey, $data, self::TTL_RESTAURANT);

        return $data;
    }

    // -------------------------------------------------------------------------
    // Search
    // -------------------------------------------------------------------------

    /**
     * Search restaurants, caching the list result by query hash.
     *
     * @param  array<string,mixed>  $criteria
     * @return array{results_found:int,start:int,count:int,restaurants:array<array<string,mixed>>}
     */
    public function search(array $criteria): array
    {
        $hash = 'zomato:search:'.md5(serialize($criteria));

        /** @var array{results_found:int,start:int,count:int,restaurants:array<array<string,mixed>>}|null $cached */
        $cached = Cache::get($hash);
        if ($cached !== null) {
            return $cached;
        }

        $result = $this->provider->search($criteria);

        foreach ($result['restaurants'] as $r) {
            $this->safeUpsertRestaurant($r, 'search');
        }

        Cache::put($hash, $result, self::TTL_SEARCH);

        return $result;
    }

    // -------------------------------------------------------------------------
    // Reviews
    // -------------------------------------------------------------------------

    /**
     * Get reviews, write-through caching + Postgres persistence.
     *
     * @return array{total:int,start:int,count:int,reviews:array<array<string,mixed>>}
     */
    public function getReviews(int $restaurantId, int $start = 0, int $count = 5): array
    {
        $cacheKey = "zomato:reviews:{$restaurantId}:{$start}:{$count}";

        /** @var array{total:int,start:int,count:int,reviews:array<array<string,mixed>>}|null $cached */
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $result = $this->provider->getReviews($restaurantId, $start, $count);

        $restaurant = Restaurant::where('zomato_id', $restaurantId)->first();
        if ($restaurant !== null) {
            foreach ($result['reviews'] as $review) {
                $this->safeUpsertReview($restaurant->id, $review);
            }
        }

        Cache::put($cacheKey, $result, self::TTL_REVIEWS);

        return $result;
    }

    // -------------------------------------------------------------------------
    // Nearby
    // -------------------------------------------------------------------------

    /**
     * Get nearby restaurants, write-through caching.
     *
     * @return array{total:int,restaurants:array<array<string,mixed>>}
     */
    public function getNearby(float $lat, float $lon, int $count = 5): array
    {
        $cacheKey = 'zomato:nearby:'.md5("{$lat}:{$lon}:{$count}");

        /** @var array{total:int,restaurants:array<array<string,mixed>>}|null $cached */
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $result = $this->provider->getNearby($lat, $lon, $count);

        foreach ($result['restaurants'] as $r) {
            $this->safeUpsertRestaurant($r, 'nearby');
        }

        Cache::put($cacheKey, $result, self::TTL_NEARBY);

        return $result;
    }

    // -------------------------------------------------------------------------
    // Daily menu
    // -------------------------------------------------------------------------

    /**
     * Get daily menu items, cached for 24h.
     *
     * @return array<array{name:string,price:string,description:string|null}>
     */
    public function getDailyMenu(int $restaurantId): array
    {
        $cacheKey = 'zomato:menu:'.$restaurantId;

        /** @var array<array{name:string,price:string,description:string|null}>|null $cached */
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $items = $this->provider->getDailyMenu($restaurantId);

        // Persist menu items — replace all for this restaurant
        $restaurant = Restaurant::where('zomato_id', $restaurantId)->first();
        if ($restaurant !== null && count($items) > 0) {
            DB::transaction(function () use ($restaurant, $items): void {
                MenuItem::where('restaurant_id', $restaurant->id)->delete();
                foreach ($items as $item) {
                    MenuItem::create([
                        'restaurant_id' => $restaurant->id,
                        'name' => $item['name'],
                        'price' => $item['price'],
                        'description' => $item['description'] ?? null,
                    ]);
                }
            });
        }

        Cache::put($cacheKey, $items, self::TTL_MENU);

        return $items;
    }

    // -------------------------------------------------------------------------
    // Internal persistence helpers
    // -------------------------------------------------------------------------

    /**
     * Best-effort upsert — never raises to the caller. Each list endpoint
     * (search/nearby) uses this so a single bad row can't fail the whole page.
     *
     * @param  array<string,mixed>  $data
     */
    private function safeUpsertRestaurant(array $data, string $source): void
    {
        try {
            $this->upsertRestaurant($data);
        } catch (InvalidArgumentException) {
            // Missing zomato id — not actionable; drop silently.
        } catch (Throwable $e) {
            Log::error('restaurant.upsert.failed', [
                'source' => $source,
                'zomato_id' => $data['id'] ?? null,
                'exception' => $e::class,
                'code' => $e->getCode(),
            ]);
        }
    }

    /**
     * Best-effort review upsert — mirror of safeUpsertRestaurant.
     *
     * @param  array<string,mixed>  $reviewData
     */
    private function safeUpsertReview(int $restaurantId, array $reviewData): void
    {
        try {
            $this->upsertReview($restaurantId, $reviewData);
        } catch (Throwable $e) {
            Log::error('review.upsert.failed', [
                'restaurant_id' => $restaurantId,
                'zomato_id' => $reviewData['id'] ?? null,
                'exception' => $e::class,
                'code' => $e->getCode(),
            ]);
        }
    }

    /**
     * Upsert a normalized restaurant array into Postgres.
     *
     * @param  array<string,mixed>  $data
     */
    public function upsertRestaurant(array $data): Restaurant
    {
        $zomatoId = (int) ($data['id'] ?? 0);

        if ($zomatoId === 0) {
            throw new InvalidArgumentException('Restaurant data missing id field.');
        }

        return DB::transaction(function () use ($data, $zomatoId): Restaurant {
            /** @var Restaurant $model */
            $model = Restaurant::updateOrCreate(
                ['zomato_id' => $zomatoId],
                [
                    'name' => $data['name'] ?? '',
                    'address' => $data['address'] ?? null,
                    'rating' => $data['rating'] ?? null,
                    'cuisines' => $data['cuisines'] ?? [],
                    'latitude' => $data['location']['lat'] ?? null,
                    'longitude' => $data['location']['lon'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'thumb_url' => $data['thumb_url'] ?? null,
                    'image_url' => $data['image_url'] ?? null,
                    'hours' => $data['hours'] ?? null,
                    'raw' => $data,
                ]
            );

            return $model;
        });
    }

    /**
     * Upsert a normalized review into Postgres.
     *
     * @param  array<string,mixed>  $reviewData
     */
    private function upsertReview(int $restaurantId, array $reviewData): void
    {
        $zomatoId = (int) ($reviewData['id'] ?? 0);

        if ($zomatoId === 0) {
            return;
        }

        Review::updateOrCreate(
            ['zomato_id' => $zomatoId],
            [
                'restaurant_id' => $restaurantId,
                'user_name' => $reviewData['user']['name'] ?? '',
                'user_thumb_url' => $reviewData['user']['thumb_url'] ?? null,
                'rating' => $reviewData['rating'] ?? 0,
                'review_text' => $reviewData['review_text'] ?? '',
                'posted_at' => $reviewData['created_at'] ?: null,
                'raw' => $reviewData,
            ]
        );
    }

    /**
     * Convert a Restaurant Eloquent model to a normalized array.
     *
     * @return array<string,mixed>
     */
    private function modelToArray(Restaurant $model): array
    {
        return [
            'id' => $model->zomato_id,
            'name' => $model->name,
            'address' => $model->address ?? '',
            'rating' => $model->rating,
            'cuisines' => $model->cuisines ?? [],
            'location' => [
                'lat' => (float) $model->latitude,
                'lon' => (float) $model->longitude,
            ],
            'thumb_url' => $model->thumb_url,
            'image_url' => $model->image_url,
            'phone' => $model->phone,
            'hours' => $model->hours,
            'menu_url' => null,
        ];
    }
}
