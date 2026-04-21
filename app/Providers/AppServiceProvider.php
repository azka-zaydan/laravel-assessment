<?php

namespace App\Providers;

use App\Auth\PassportTokenGuard;
use App\Models\User;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Laravel\Passport\PassportUserProvider;
use League\OAuth2\Server\ResourceServer;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(function () {
            return Password::min(8)
                ->letters()
                ->mixedCase()
                ->numbers();
        });

        Passport::tokensExpireIn(now()->addMinutes(15));

        Gate::define('admin', function (User $user): bool {
            return (bool) $user->is_admin;
        });

        // Override Passport's guard to reset the cached user on each new request.
        // This ensures token revocation is respected within a single test run where
        // multiple simulated HTTP requests share the same application instance.
        Auth::resolved(function ($auth): void {
            $auth->extend('passport', function (Application $app, string $name, array $config): PassportTokenGuard {
                $provider = Auth::createUserProvider($config['provider']);

                if (! ($provider instanceof UserProvider)) {
                    throw new \RuntimeException("Could not resolve user provider [{$config['provider']}].");
                }

                $guard = new PassportTokenGuard(
                    $app->make(ResourceServer::class),
                    new PassportUserProvider($provider, $config['provider']),
                    $app->make(ClientRepository::class),
                    $app->make('encrypter'),
                    $app->make('request'),
                );

                $app->refresh('request', $guard, 'setRequest');

                return $guard;
            });
        });
    }
}
