<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Restaurants\NearbyRequest;
use App\Http\Requests\Restaurants\SearchRequest;
use App\Http\Resources\MenuItemResource;
use App\Http\Resources\RestaurantResource;
use App\Http\Resources\ReviewResource;
use App\Services\Restaurants\RestaurantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RestaurantController extends Controller
{
    public function __construct(
        private readonly RestaurantService $service,
    ) {}

    /**
     * GET /api/restaurants
     */
    public function index(SearchRequest $request): JsonResponse
    {
        $result = $this->service->search($request->criteria());

        return response()->json([
            'data' => array_map(
                fn ($r) => (new RestaurantResource($r))->toArray($request),
                $result['restaurants']
            ),
            'meta' => [
                'total' => $result['results_found'],
                'start' => $result['start'],
                'count' => $result['count'],
            ],
        ]);
    }

    /**
     * GET /api/restaurants/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $restaurant = $this->service->getRestaurant($id);

        if ($restaurant === null) {
            return response()->json(['error' => 'not found'], 404);
        }

        return response()->json([
            'data' => (new RestaurantResource($restaurant))->toArray($request),
        ]);
    }

    /**
     * GET /api/restaurants/{id}/reviews
     */
    public function reviews(Request $request, int $id): JsonResponse
    {
        if ($this->service->getRestaurant($id) === null) {
            return response()->json(['error' => 'not found'], 404);
        }

        $start = max(0, (int) $request->query('start', 0));
        $count = min(20, max(1, (int) $request->query('count', 5)));

        $result = $this->service->getReviews($id, $start, $count);

        return response()->json([
            'data' => array_map(
                fn ($r) => (new ReviewResource($r))->toArray($request),
                $result['reviews']
            ),
            'meta' => [
                'total' => $result['total'],
                'start' => $result['start'],
                'count' => $result['count'],
            ],
        ]);
    }

    /**
     * GET /api/restaurants/nearby
     */
    public function nearby(NearbyRequest $request): JsonResponse
    {
        $lat = (float) $request->input('lat');
        $lon = (float) $request->input('lon');
        $count = min(20, max(1, (int) $request->input('count', 5)));

        $result = $this->service->getNearby($lat, $lon, $count);

        $data = array_map(function ($r) use ($request, $lat, $lon) {
            $resource = (new RestaurantResource($r))->toArray($request);

            // Compute distance here so every provider (Zomato, Foursquare,
            // OSM, Fixture) gets distance_meters populated uniformly
            // without each having to implement the Haversine.
            $resource['distance_meters'] = $this->distanceMeters(
                $lat,
                $lon,
                (float) ($r['location']['lat'] ?? 0),
                (float) ($r['location']['lon'] ?? 0),
            );

            return $resource;
        }, $result['restaurants']);

        // Sort nearest-first. Providers vary in whether upstream sorts —
        // Foursquare with sort=distance does, FixtureProvider/OSM Overpass
        // don't — so we normalise client-facing ordering here.
        // Rows without coordinates (distance_meters === null) sink to the
        // bottom so they don't pollute the "closest" head of the list.
        usort($data, static fn (array $a, array $b): int => match (true) {
            $a['distance_meters'] === null && $b['distance_meters'] === null => 0,
            $a['distance_meters'] === null => 1,
            $b['distance_meters'] === null => -1,
            default => $a['distance_meters'] <=> $b['distance_meters'],
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'total' => $result['total'],
            ],
        ]);
    }

    /**
     * Great-circle distance in whole metres between two WGS84 points.
     * Accurate enough for "nearest restaurant" UX (error < 0.5% at Jakarta
     * latitudes) and avoids a PostGIS dependency.
     */
    private function distanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): ?int
    {
        if ($lat2 === 0.0 && $lon2 === 0.0) {
            // Missing coords on the result — don't fabricate a "distance to
            // null island". null communicates "unknown" honestly.
            return null;
        }

        $earthRadius = 6_371_000; // metres
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return (int) round($earthRadius * $c);
    }

    /**
     * GET /api/restaurants/{id}/menu
     */
    public function menu(Request $request, int $id): JsonResponse
    {
        if ($this->service->getRestaurant($id) === null) {
            return response()->json(['error' => 'not found'], 404);
        }

        $items = $this->service->getDailyMenu($id);

        return response()->json([
            'data' => array_map(
                fn ($item) => (new MenuItemResource($item))->toArray($request),
                $items
            ),
        ]);
    }
}
