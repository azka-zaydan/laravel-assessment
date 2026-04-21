<?php

use App\Http\Controllers\Api\Admin\ApiLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RestaurantController;
use App\Http\Controllers\Api\TwoFactorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Auth routes
|--------------------------------------------------------------------------
*/

Route::post('/register', [AuthController::class, 'register']);

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:6,1');

Route::post('/auth/refresh', [AuthController::class, 'refresh']);

Route::middleware('auth:api')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    /*
    |--------------------------------------------------------------------------
    | 2FA management (requires auth but NOT require_2fa — user sets it up here)
    |--------------------------------------------------------------------------
    */
    Route::prefix('2fa')->group(function (): void {
        Route::post('/enable', [TwoFactorController::class, 'enable']);
        Route::post('/confirm', [TwoFactorController::class, 'confirm']);

        Route::middleware('require_2fa')->group(function (): void {
            Route::post('/recovery-codes/regenerate', [TwoFactorController::class, 'regenerate']);
        });
    });
});

/*
|--------------------------------------------------------------------------
| 2FA verify — no auth:api, uses challenge_token from body
|--------------------------------------------------------------------------
*/
Route::post('/2fa/verify', [TwoFactorController::class, 'verify'])
    ->middleware('throttle:6,1');

/*
|--------------------------------------------------------------------------
| Admin routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:api', 'require_2fa'])->prefix('admin')->group(function (): void {
    Route::get('/api-logs', [ApiLogController::class, 'index'])->middleware('can:admin');
});

/*
|--------------------------------------------------------------------------
| Restaurant routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:api', 'require_2fa'])->prefix('restaurants')->group(function (): void {
    Route::get('/', [RestaurantController::class, 'index']);
    Route::get('/nearby', [RestaurantController::class, 'nearby']);
    Route::get('/{id}', [RestaurantController::class, 'show'])->whereNumber('id');
    Route::get('/{id}/reviews', [RestaurantController::class, 'reviews'])->whereNumber('id');
    Route::get('/{id}/menu', [RestaurantController::class, 'menu'])->whereNumber('id');
});
