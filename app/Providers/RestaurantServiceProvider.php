<?php

namespace App\Providers;

use App\Repositories\RestaurantRepository;
use App\Services\Restaurants\MockProvider;
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
        // Bind the concrete provider based on config
        $this->app->singleton(RestaurantProvider::class, function (Application $app): RestaurantProvider {
            $driver = config('services.restaurants.provider', 'mock');

            if ($driver === 'zomato') {
                return new ZomatoProvider(
                    baseUrl: (string) config('services.zomato.base_url', 'https://developers.zomato.com/api/v2.1'),
                    userKey: (string) config('services.zomato.user_key', ''),
                );
            }

            return new MockProvider;
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
