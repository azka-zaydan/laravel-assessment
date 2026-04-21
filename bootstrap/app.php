<?php

use App\Http\Middleware\LogApiRequest;
use App\Http\Middleware\Require2FA;
use App\Http\Middleware\RequireTwoFactorConfirmed;
use App\Http\Middleware\ValidateTelegramSecret;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
