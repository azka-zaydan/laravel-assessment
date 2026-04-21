<?php

namespace App\Services\Restaurants;

use App\Repositories\RestaurantRepository;
use App\Support\ZomatoRateLimiter;
use Illuminate\Support\Facades\Log;

class RestaurantService
{
    public function __construct(
        private readonly RestaurantRepository $repo,
        private readonly ZomatoRateLimiter $rateLimiter,
    ) {}

    /**
     * Search restaurants.
     *
     * @param  array<string,mixed>  $criteria
     * @return array{results_found:int,start:int,count:int,restaurants:array<array<string,mixed>>}
     */
    public function search(array $criteria): array
    {
        if (! $this->rateLimiter->attempt()) {
            Log::warning('RestaurantService: rate limit hit, returning empty search result');

            return [
                'results_found' => 0,
                'start' => (int) ($criteria['start'] ?? 0),
                'count' => 0,
                'restaurants' => [],
            ];
        }

        return $this->repo->search($criteria);
    }

    /**
     * Get a single restaurant by Zomato ID.
     *
     * @return array<string,mixed>|null
     */
    public function getRestaurant(int $id): ?array
    {
        if (! $this->rateLimiter->attempt()) {
            Log::warning('RestaurantService: rate limit hit, returning null for restaurant', ['id' => $id]);

            return null;
        }

        return $this->repo->findOrFetchRestaurant($id);
    }

    /**
     * Get reviews for a restaurant.
     *
     * @return array{total:int,start:int,count:int,reviews:array<array<string,mixed>>}
     */
    public function getReviews(int $restaurantId, int $start = 0, int $count = 5): array
    {
        if (! $this->rateLimiter->attempt()) {
            Log::warning('RestaurantService: rate limit hit, returning empty reviews', ['id' => $restaurantId]);

            return [
                'total' => 0,
                'start' => $start,
                'count' => 0,
                'reviews' => [],
            ];
        }

        return $this->repo->getReviews($restaurantId, $start, $count);
    }

    /**
     * Get nearby restaurants.
     *
     * @return array{total:int,restaurants:array<array<string,mixed>>}
     */
    public function getNearby(float $lat, float $lon, int $count = 5): array
    {
        if (! $this->rateLimiter->attempt()) {
            Log::warning('RestaurantService: rate limit hit, returning empty nearby result');

            return ['total' => 0, 'restaurants' => []];
        }

        return $this->repo->getNearby($lat, $lon, $count);
    }

    /**
     * Get the daily menu for a restaurant.
     *
     * @return array<array{name:string,price:string,description:string|null}>
     */
    public function getDailyMenu(int $restaurantId): array
    {
        if (! $this->rateLimiter->attempt()) {
            Log::warning('RestaurantService: rate limit hit, returning empty menu', ['id' => $restaurantId]);

            return [];
        }

        return $this->repo->getDailyMenu($restaurantId);
    }
}
