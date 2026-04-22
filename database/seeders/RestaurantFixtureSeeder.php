<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\Review;
use Illuminate\Database\Seeder;

/**
 * Seeds the restaurants/reviews/menu_items tables from the Zomato-shaped
 * JSON fixtures under database/fixtures/zomato/.
 *
 * Idempotent — every write is upsert-by-zomato_id, so running the seeder
 * N times produces the same row count as running it once. Safe to call on
 * every container boot (see docker/entrypoint.sh on the web service).
 *
 * The fixtures are the on-disk source of truth; Postgres is the runtime
 * store that ZomatoStubController reads from when serving /zomato/api/v2.1.
 */
class RestaurantFixtureSeeder extends Seeder
{
    public function run(): void
    {
        $dir = base_path('database/fixtures/zomato');

        if (! is_dir($dir)) {
            $this->command->warn("RestaurantFixtureSeeder: fixtures dir not found at {$dir}");

            return;
        }

        $searchFile = $dir.'/search.json';
        if (file_exists($searchFile)) {
            $this->seedFromSearchFixture($searchFile);
        }

        // Per-restaurant fixtures override the search list (fuller detail
        // records like restaurant_16507621.json carry extra fields the
        // abbreviated search node lacks).
        foreach (glob($dir.'/restaurant_*.json') ?: [] as $path) {
            if (str_ends_with($path, 'restaurant_not_found.json')) {
                continue;
            }
            $this->seedOneRestaurantFile($path);
        }

        foreach (glob($dir.'/reviews_*.json') ?: [] as $path) {
            $this->seedReviewsFile($path);
        }

        foreach (glob($dir.'/dailymenu_*.json') ?: [] as $path) {
            $this->seedMenuFile($path);
        }
    }

    private function seedFromSearchFixture(string $path): void
    {
        /** @var array{restaurants?:array<array<string,mixed>>}|null $data */
        $data = $this->loadJson($path);
        if ($data === null) {
            return;
        }

        foreach ($data['restaurants'] ?? [] as $entry) {
            $r = $entry['restaurant'] ?? $entry;
            $this->upsertRestaurant($r);
        }
    }

    private function seedOneRestaurantFile(string $path): void
    {
        /** @var array<string,mixed>|null $data */
        $data = $this->loadJson($path);
        if ($data === null) {
            return;
        }

        $node = $data['restaurant'] ?? $data;
        $this->upsertRestaurant($node);
    }

    /**
     * @param  array<string,mixed>  $r
     */
    private function upsertRestaurant(array $r): void
    {
        $zomatoId = (int) ($r['R']['res_id'] ?? $r['id'] ?? 0);

        if ($zomatoId === 0) {
            return;
        }

        /** @var array<string,mixed> $location */
        $location = $r['location'] ?? [];

        /** @var array<string,mixed> $userRating */
        $userRating = $r['user_rating'] ?? [];

        Restaurant::updateOrCreate(
            ['zomato_id' => $zomatoId],
            [
                'name' => (string) ($r['name'] ?? ''),
                'address' => (string) ($location['address'] ?? ''),
                'rating' => isset($userRating['aggregate_rating'])
                    ? (float) $userRating['aggregate_rating']
                    : null,
                'cuisines' => array_values(array_filter(
                    array_map('trim', explode(',', (string) ($r['cuisines'] ?? '')))
                )),
                'latitude' => isset($location['latitude']) ? (float) $location['latitude'] : null,
                'longitude' => isset($location['longitude']) ? (float) $location['longitude'] : null,
                'phone' => ($r['phone_numbers'] ?? null) ?: null,
                'thumb_url' => ($r['thumb'] ?? null) ?: null,
                'image_url' => ($r['featured_image'] ?? null) ?: null,
                'hours' => ($r['timings'] ?? null) ?: null,
                'raw' => $r,
            ]
        );
    }

    private function seedReviewsFile(string $path): void
    {
        if (! preg_match('/reviews_(\d+)\.json$/', $path, $m)) {
            return;
        }
        $zomatoId = (int) $m[1];
        $restaurant = Restaurant::where('zomato_id', $zomatoId)->first();
        if ($restaurant === null) {
            return;
        }

        /** @var array{user_reviews?:array<array<string,mixed>>}|null $data */
        $data = $this->loadJson($path);
        if ($data === null) {
            return;
        }

        foreach ($data['user_reviews'] ?? [] as $wrapper) {
            $review = $wrapper['review'] ?? $wrapper;
            $reviewId = (int) ($review['id'] ?? 0);
            if ($reviewId === 0) {
                continue;
            }

            /** @var array<string,mixed> $user */
            $user = $review['user'] ?? [];

            Review::updateOrCreate(
                ['zomato_id' => $reviewId],
                [
                    'restaurant_id' => $restaurant->id,
                    'user_name' => (string) ($user['name'] ?? 'Anonymous'),
                    'user_thumb_url' => ($user['profile_image'] ?? null) ?: null,
                    'rating' => (int) ($review['rating'] ?? 0),
                    'review_text' => (string) ($review['review_text'] ?? ''),
                    'posted_at' => isset($review['timestamp']) && is_numeric($review['timestamp'])
                        ? date('Y-m-d H:i:s', (int) $review['timestamp'])
                        : null,
                    'raw' => $review,
                ]
            );
        }
    }

    private function seedMenuFile(string $path): void
    {
        if (! preg_match('/dailymenu_(\d+)\.json$/', $path, $m)) {
            return;
        }
        $zomatoId = (int) $m[1];
        $restaurant = Restaurant::where('zomato_id', $zomatoId)->first();
        if ($restaurant === null) {
            return;
        }

        /** @var array{daily_menus?:array<array<string,mixed>>}|null $data */
        $data = $this->loadJson($path);
        if ($data === null) {
            return;
        }

        // Collect every dish across all daily_menus entries in the fixture.
        $dishes = [];
        foreach ($data['daily_menus'] ?? [] as $wrapper) {
            $menu = $wrapper['daily_menu'] ?? $wrapper;
            foreach ($menu['dishes'] ?? [] as $dishWrapper) {
                $dish = $dishWrapper['dish'] ?? $dishWrapper;
                $dishId = (int) ($dish['dish_id'] ?? 0);
                if ($dishId === 0) {
                    continue;
                }
                $dishes[$dishId] = [
                    'name' => (string) ($dish['name'] ?? ''),
                    'price' => (string) ($dish['price'] ?? ''),
                ];
            }
        }

        // Wipe + rewrite menu for deterministic state. (Menu items have no
        // upstream-stable ID we can dedupe on beyond dish_id, which fixtures
        // don't guarantee across runs, so replace-all is the safe idempotent
        // operation here.)
        MenuItem::where('restaurant_id', $restaurant->id)->delete();
        foreach ($dishes as $d) {
            MenuItem::create([
                'restaurant_id' => $restaurant->id,
                'name' => $d['name'],
                'price' => $d['price'],
                'description' => null,
            ]);
        }
    }

    /**
     * @return array<mixed>|null
     */
    private function loadJson(string $path): ?array
    {
        $raw = @file_get_contents($path);
        if ($raw === false) {
            return null;
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }
}
