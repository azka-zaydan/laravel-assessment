<?php

namespace App\Providers;

use App\Repositories\RestaurantRepository;
use App\Services\Restaurants\FixtureProvider;
use App\Services\Restaurants\FoursquareProvider;
use App\Services\Restaurants\OsmProvider;
use App\Services\Restaurants\RestaurantProvider;
use App\Services\Restaurants\RestaurantService;
use App\Services\Restaurants\ZomatoProvider;
use App\Support\ZomatoRateLimiter;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class RestaurantServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind the concrete provider based on the RESTAURANT_PROVIDER env.
        //
        //   zomato      → ZomatoProvider — real HTTP calls to ZOMATO_BASE_URL
        //                 (which points at our in-app /zomato/api/v2.1/* stub
        //                 backed by Postgres).
        //   foursquare  → FoursquareProvider — real HTTP to
        //                 places-api.foursquare.com, free Pro tier (search +
        //                 nearby only; Premium endpoints deliberately skipped).
        //   osm         → OsmProvider — Nominatim + Overpass, no API key,
        //                 no rating/review/menu data but real Jakarta POIs.
        //   fixture     → FixtureProvider — reads JSON from
        //                 database/fixtures/zomato directly, no network.
        //                 Used by the test suite for speed + hermeticity.
        //
        // Default is `zomato` so deploys with no env configured still serve
        // the fully-populated internal stub.
        $this->app->singleton(RestaurantProvider::class, function (Application $app): RestaurantProvider {
            $driver = (string) config('services.restaurants.provider', 'zomato');

            return match ($driver) {
                'fixture' => new FixtureProvider,
                'foursquare' => new FoursquareProvider(
                    baseUrl: (string) config('services.foursquare.base_url', 'https://places-api.foursquare.com'),
                    apiKey: (string) config('services.foursquare.api_key', ''),
                    apiVersion: (string) config('services.foursquare.api_version', '2025-06-17'),
                ),
                'osm' => new OsmProvider(
                    nominatimBaseUrl: (string) config('services.osm.nominatim_base_url', 'https://nominatim.openstreetmap.org'),
                    overpassBaseUrl: (string) config('services.osm.overpass_base_url', 'https://overpass-api.de/api/interpreter'),
                    userAgent: (string) config('services.osm.user_agent', 'laravel-culinary-bot/1.0'),
                ),
                default => new ZomatoProvider(
                    baseUrl: (string) config('services.zomato.base_url', 'https://laravel.catatkeu.app/zomato/api/v2.1'),
                    userKey: (string) config('services.zomato.user_key', ''),
                ),
            };
        });

        // Rate limiter singleton
        $this->app->singleton(ZomatoRateLimiter::class);

        // Repository — resolves the bound RestaurantProvider automatically
        $this->app->singleton(RestaurantRepository::class, function (Application $app): RestaurantRepository {
            return new RestaurantRepository(
                provider: $app->make(RestaurantProvider::class),
            );
        });

        // Service layer
        $this->app->singleton(RestaurantService::class, function (Application $app): RestaurantService {
            return new RestaurantService(
                repo: $app->make(RestaurantRepository::class),
                rateLimiter: $app->make(ZomatoRateLimiter::class),
            );
        });
    }
}
