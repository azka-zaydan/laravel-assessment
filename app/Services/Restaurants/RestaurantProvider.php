<?php

namespace App\Services\Restaurants;

/**
 * Strategy interface for restaurant data providers.
 *
 * All methods return normalized arrays with the following shapes:
 *
 * Restaurant shape:
 *   id (int), name (string), address (string), rating (float|null),
 *   cuisines (string[]), location (array{lat:float,lon:float}),
 *   thumb_url (string|null), image_url (string|null),
 *   phone (string|null), hours (string|null), menu_url (string|null)
 *
 * Review shape:
 *   id (int), rating (float), review_text (string),
 *   user (array{name:string,thumb_url:string|null}), created_at (string)
 *
 * Nearby restaurant shape (extends Restaurant):
 *   + distance_meters (int|null)
 *
 * Menu item shape:
 *   name (string), price (string), description (string|null)
 *
 * City shape:
 *   id (int), name (string), country_name (string|null)
 *
 * Cuisine shape:
 *   id (int), name (string)
 *
 * Search result shape:
 *   results_found (int), start (int), count (int),
 *   restaurants (array<array{...restaurant shape...}>)
 */
interface RestaurantProvider
{
    /**
     * Search restaurants by criteria.
     *
     * @param  array{q?:string,lat?:float,lon?:float,cuisine?:string,count?:int,start?:int}  $criteria
     * @return array{results_found:int,start:int,count:int,restaurants:array<array<string,mixed>>}
     */
    public function search(array $criteria): array;

    /**
     * Get a single restaurant by ID.
     *
     * @return array<string,mixed>|null
     */
    public function getRestaurant(int $id): ?array;

    /**
     * Get reviews for a restaurant.
     *
     * @return array{total:int,start:int,count:int,reviews:array<array<string,mixed>>}
     */
    public function getReviews(int $id, int $start = 0, int $count = 5): array;

    /**
     * Get nearby restaurants for a location.
     *
     * @return array{total:int,restaurants:array<array<string,mixed>>}
     */
    public function getNearby(float $lat, float $lon, int $count = 5): array;

    /**
     * Get daily menu items for a restaurant.
     *
     * @return array<array{name:string,price:string,description:string|null}>
     */
    public function getDailyMenu(int $id): array;

    /**
     * Search cities.
     *
     * @return array<array{id:int,name:string,country_name:string|null}>
     */
    public function getCities(?string $q = null, ?float $lat = null, ?float $lon = null): array;

    /**
     * Get cuisines for a city.
     *
     * @return array<array{id:int,name:string}>
     */
    public function getCuisines(int $cityId): array;
}
