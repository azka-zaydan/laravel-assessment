<?php

namespace App\Http\Controllers;

use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\Review;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Zomato-shaped HTTP endpoint backed by our own Postgres database.
 *
 * Zomato's public developer API (developers.zomato.com) was shut down years
 * ago. The brief still specifies Zomato as the restaurant data source, so
 * we host an internal HTTP endpoint that mimics Zomato's v2.1 response
 * contract (per the Vivek-Raj SwaggerHub spec). ZomatoProvider makes real
 * outbound HTTP calls to this endpoint in production exactly as it would
 * against the live upstream — same request shape, same response envelope,
 * same field names.
 *
 * The data lives in the restaurants / reviews / menu_items tables,
 * populated by RestaurantFixtureSeeder (idempotent, keyed on zomato_id).
 * No file I/O on the read path in production.
 *
 * Not inside the `api` middleware group: no auth, no request-logging —
 * logging our own stub calls would double-count every inbound API request.
 */
class ZomatoStubController extends Controller
{
    /**
     * Reverse the internal Restaurant row into Zomato's v2.1 envelope shape.
     *
     * @return array{restaurant:array<string,mixed>}
     */
    private function toZomatoEnvelope(Restaurant $r): array
    {
        // `cuisines` is JSONB with an array cast on the model, so it is
        // always an array (or null when the column is empty).
        /** @var array<int,string>|null $rawCuisines */
        $rawCuisines = $r->cuisines;
        $cuisines = $rawCuisines ?? [];

        return [
            'restaurant' => [
                'R' => ['res_id' => $r->zomato_id],
                'id' => (string) $r->zomato_id,
                'name' => $r->name,
                'url' => "https://www.zomato.com/restaurants/{$r->zomato_id}",
                'location' => [
                    'address' => $r->address ?? '',
                    'locality' => '',
                    'city' => 'Jakarta',
                    'city_id' => 74,
                    'latitude' => (string) ($r->latitude ?? ''),
                    'longitude' => (string) ($r->longitude ?? ''),
                    'country_id' => 94,
                ],
                'cuisines' => implode(', ', $cuisines),
                'timings' => $r->hours ?? '',
                'currency' => 'Rp',
                'thumb' => $r->thumb_url ?? '',
                'featured_image' => $r->image_url ?? '',
                'phone_numbers' => $r->phone ?? '',
                'user_rating' => [
                    'aggregate_rating' => (string) ($r->rating ?? '0'),
                    'rating_text' => ($r->rating ?? 0) >= 4.0 ? 'Very Good' : 'Good',
                    'rating_color' => '5BA829',
                    'votes' => 0,
                ],
                'menu_url' => "https://www.zomato.com/restaurants/{$r->zomato_id}/menu",
            ],
        ];
    }

    /**
     * GET /zomato/api/v2.1/search
     * ?q=<query>&count=<n>&start=<offset>
     */
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $start = max(0, (int) $request->query('start', 0));
        $count = max(1, min(20, (int) $request->query('count', 20)));

        $query = Restaurant::query();

        if ($q !== '') {
            $needle = '%'.mb_strtolower($q).'%';
            $query->where(function (Builder $inner) use ($needle): void {
                $inner->whereRaw('LOWER(name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(address) LIKE ?', [$needle])
                    // JSONB array: cuisines::text is the textual representation,
                    // which contains every element — good enough for a substring hit.
                    ->orWhereRaw('LOWER(cuisines::text) LIKE ?', [$needle]);
            });
        }

        $total = (clone $query)->count();
        $restaurants = $query->orderBy('id')->offset($start)->limit($count)->get();

        $envelopes = [];
        foreach ($restaurants as $r) {
            $envelopes[] = $this->toZomatoEnvelope($r);
        }

        return response()->json([
            'results_found' => $total,
            'results_start' => $start,
            'results_shown' => $restaurants->count(),
            'restaurants' => $envelopes,
        ]);
    }

    /**
     * GET /zomato/api/v2.1/restaurant?res_id=<id>
     */
    public function restaurant(Request $request): JsonResponse
    {
        $resId = (int) $request->query('res_id', 0);

        if ($resId === 0) {
            return response()->json(['success' => false, 'message' => 'res_id is required'], 400);
        }

        $r = Restaurant::where('zomato_id', $resId)->first();

        if ($r === null) {
            return response()->json(['success' => false, 'message' => 'Invalid res_id.'], 400);
        }

        // Wrap under a "restaurant" key to match the Zomato v2.1 contract —
        // clients that parse `$body.restaurant.name` would break without it
        // (our own ZomatoProvider tolerates both shapes, but we should still
        // be contract-correct for any external consumer following the spec).
        return response()->json($this->toZomatoEnvelope($r));
    }

    /**
     * GET /zomato/api/v2.1/reviews?res_id=<id>&start=<offset>&count=<n>
     */
    public function reviews(Request $request): JsonResponse
    {
        $resId = (int) $request->query('res_id', 0);
        $start = max(0, (int) $request->query('start', 0));
        $count = max(1, min(20, (int) $request->query('count', 5)));

        if ($resId === 0) {
            return response()->json(['success' => false, 'message' => 'res_id is required'], 400);
        }

        $restaurant = Restaurant::where('zomato_id', $resId)->first();

        if ($restaurant === null) {
            return response()->json([
                'user_reviews' => [],
                'reviews_count' => 0,
                'reviews_start' => $start,
                'reviews_shown' => 0,
            ]);
        }

        $total = Review::where('restaurant_id', $restaurant->id)->count();
        $rows = Review::where('restaurant_id', $restaurant->id)
            ->orderByDesc('posted_at')
            ->offset($start)
            ->limit($count)
            ->get();

        $userReviews = [];
        foreach ($rows as $review) {
            $postedAt = $review->posted_at?->timestamp;
            $userReviews[] = [
                'review' => [
                    'id' => $review->zomato_id,
                    'rating' => $review->rating,
                    'review_text' => $review->review_text,
                    'timestamp' => $postedAt,
                    'user' => [
                        'name' => $review->user_name,
                        'profile_image' => $review->user_thumb_url,
                    ],
                ],
            ];
        }

        return response()->json([
            'reviews_count' => $total,
            'reviews_start' => $start,
            'reviews_shown' => $rows->count(),
            'user_reviews' => $userReviews,
        ]);
    }

    /**
     * GET /zomato/api/v2.1/dailymenu?res_id=<id>
     */
    public function dailymenu(Request $request): JsonResponse
    {
        $resId = (int) $request->query('res_id', 0);

        if ($resId === 0) {
            return response()->json(['success' => false, 'message' => 'res_id is required'], 400);
        }

        $restaurant = Restaurant::where('zomato_id', $resId)->first();

        if ($restaurant === null) {
            return response()->json(['daily_menus' => []]);
        }

        $items = MenuItem::where('restaurant_id', $restaurant->id)->orderBy('id')->get();

        if ($items->isEmpty()) {
            return response()->json(['daily_menus' => []]);
        }

        $dishes = [];
        foreach ($items as $m) {
            $dishes[] = [
                'dish' => [
                    'dish_id' => $m->id,
                    'name' => $m->name,
                    'price' => $m->price,
                ],
            ];
        }

        return response()->json([
            'daily_menus' => [
                [
                    'daily_menu' => [
                        'daily_menu_id' => $restaurant->zomato_id,
                        'name' => 'Daily Menu',
                        'start_date' => now()->format('Y-m-d H:i'),
                        'end_date' => now()->addDay()->format('Y-m-d H:i'),
                        'dishes' => $dishes,
                    ],
                ],
            ],
        ]);
    }

    /**
     * GET /zomato/api/v2.1/geocode?lat=..&lon=..
     * Returns the five restaurants closest to the given coordinates, in
     * Zomato's nearby_restaurants shape.
     */
    public function geocode(Request $request): JsonResponse
    {
        $lat = (float) $request->query('lat', -6.2);
        $lon = (float) $request->query('lon', 106.8);

        // Rough distance ordering via squared-degree deltas — good enough
        // for the half-dozen rows we return and avoids a PostGIS dependency.
        $nearby = Restaurant::whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->selectRaw('*, ((latitude - ?) * (latitude - ?) + (longitude - ?) * (longitude - ?)) AS dist_sq', [$lat, $lat, $lon, $lon])
            ->orderBy('dist_sq')
            ->limit(5)
            ->get();

        return response()->json([
            'location' => [
                'entity_type' => 'city',
                'entity_id' => 74,
                'title' => 'Jakarta',
                'latitude' => $lat,
                'longitude' => $lon,
                'city_id' => 74,
                'city_name' => 'Jakarta',
                'country_id' => 94,
                'country_name' => 'Indonesia',
            ],
            'popularity' => [
                'popularity' => '4.5',
                'nightlife_index' => '4.0',
                'top_cuisines' => ['Indonesian', 'Japanese', 'Italian'],
            ],
            'nearby_restaurants' => array_map(
                fn (Restaurant $r) => $this->toZomatoEnvelope($r),
                $nearby->all(),
            ),
        ]);
    }

    /**
     * GET /zomato/api/v2.1/cities?q=<name>
     */
    public function cities(Request $request): JsonResponse
    {
        return response()->json([
            'location_suggestions' => [
                [
                    'id' => 74,
                    'name' => 'Jakarta',
                    'country_id' => 94,
                    'country_name' => 'Indonesia',
                    'is_state' => 0,
                    'state_id' => 0,
                    'state_name' => '',
                    'state_code' => '',
                ],
            ],
            'status' => 'success',
            'has_more' => 0,
            'has_total' => 1,
        ]);
    }

    /**
     * GET /zomato/api/v2.1/cuisines?city_id=<id>
     * Derived live from the cuisines JSONB column across all restaurants.
     */
    public function cuisines(Request $request): JsonResponse
    {
        /** @var array<int,string> $all */
        $all = [];
        foreach (Restaurant::query()->pluck('cuisines') as $list) {
            if (is_array($list)) {
                foreach ($list as $c) {
                    $c = trim((string) $c);
                    if ($c !== '') {
                        $all[] = $c;
                    }
                }
            }
        }

        $unique = array_values(array_unique($all));
        sort($unique);

        return response()->json([
            'cuisines' => array_map(
                fn (string $name, int $idx) => [
                    'cuisine' => ['cuisine_id' => $idx + 1, 'cuisine_name' => $name],
                ],
                $unique,
                array_keys($unique)
            ),
        ]);
    }
}
