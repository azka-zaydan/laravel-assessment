<?php

namespace App\Providers;

use App\Repositories\RestaurantRepository;
use App\Services\Restaurants\FixtureProvider;
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
        // Bind the concrete provider based on config. Production uses
        // `zomato` → ZomatoProvider (real HTTP calls to ZOMATO_BASE_URL,
        // which points at our in-app /zomato/api/v2.1/* stub). Tests use
        // `fixture` → FixtureProvider (reads the same JSON files from
        // database/fixtures/zomato/ directly, no HTTP, no network).
        $this->app->singleton(RestaurantProvider::class, function (Application $app): RestaurantProvider {
            $driver = config('services.restaurants.provider', 'zomato');

            if ($driver === 'fixture') {
                return new FixtureProvider;
            }

            return new ZomatoProvider(
                baseUrl: (string) config('services.zomato.base_url', 'https://laravel.catatkeu.app/zomato/api/v2.1'),
                userKey: (string) config('services.zomato.user_key', ''),
            );
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
