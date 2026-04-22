<?php

use App\Http\Middleware\LogApiRequest;
use App\Http\Middleware\Require2FA;
use App\Http\Middleware\RequireTwoFactorConfirmed;
use App\Http\Middleware\ValidateTelegramSecret;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'require_2fa' => Require2FA::class,
            'require_2fa_confirmed' => RequireTwoFactorConfirmed::class,
            'telegram.secret' => ValidateTelegramSecret::class,
        ]);

        // LogApiRequest must run AFTER auth:api so $request->user() is populated.
        // appendToGroup places it last in the api middleware stack.
        $middleware->appendToGroup('api', LogApiRequest::class);

        // API-only app: never try to resolve route('login'). The default
        // Authenticate middleware eagerly calls route('login') inside
        // redirectTo() for non-JSON requests — which throws
        // RouteNotFoundException (→ 500) because no such route exists.
        // Returning null makes AuthenticationException propagate cleanly
        // to the exception handler, which then renders JSON via
        // shouldRenderJsonWhen below.
        $middleware->redirectGuestsTo(fn (): ?string => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Force JSON error responses for every /api/* request regardless of
        // the client's Accept header. This catches any current or future
        // exception on api/* (auth, validation, 404, 500) and keeps the wire
        // format consistent for consumers.
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e): bool {
            return $request->is('api/*') || $request->expectsJson();
        });
    })->create();
