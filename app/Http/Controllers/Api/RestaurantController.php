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

        return response()->json([
            'data' => array_map(function ($r) use ($request) {
                $resource = (new RestaurantResource($r))->toArray($request);
                $resource['distance_meters'] = $r['distance_meters'] ?? null;

                return $resource;
            }, $result['restaurants']),
            'meta' => [
                'total' => $result['total'],
            ],
        ]);
    }

    /**
     * GET /api/restaurants/{id}/menu
     */
    public function menu(Request $request, int $id): JsonResponse
    {
        $items = $this->service->getDailyMenu($id);

        return response()->json([
            'data' => array_map(
                fn ($item) => (new MenuItemResource($item))->toArray($request),
                $items
            ),
        ]);
    }
}
