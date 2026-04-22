<?php

use App\Http\Controllers\ZomatoStubController;
use Illuminate\Support\Facades\Route;

// Public landing page (rendered by the same React SPA shell as /admin/*).
Route::get('/', fn () => view('admin.app'));

// Admin SPA catch-all — serve the React shell for every /admin/* path.
Route::get('/admin/{any?}', fn () => view('admin.app'))->where('any', '.*');

/*
|--------------------------------------------------------------------------
| Zomato stub — Zomato-shaped HTTP endpoint served from our own origin.
|--------------------------------------------------------------------------
|
| Zomato's public developer API is shut down. The brief specifies Zomato
| as the data source, so we host an in-app HTTP endpoint that speaks the
| v2.1 response contract (per the Vivek-Raj SwaggerHub spec). In production
| ZomatoProvider makes real HTTP calls to this URL — same request shape,
| same response envelope as the original upstream.
|
| The backing data lives in the restaurants / reviews / menu_items tables
| (seeded by RestaurantFixtureSeeder, idempotent). No file I/O on the
| read path.
|
| Mounted under /zomato/api/v2.1 to match the original Zomato base path.
| Not inside the `api` middleware group: no auth, no request-logging (we
| don't want the stub's own calls echoing into api_logs on every search).
*/
Route::prefix('zomato/api/v2.1')->group(function (): void {
    Route::get('/search', [ZomatoStubController::class, 'search']);
    Route::get('/restaurant', [ZomatoStubController::class, 'restaurant']);
    Route::get('/reviews', [ZomatoStubController::class, 'reviews']);
    Route::get('/dailymenu', [ZomatoStubController::class, 'dailymenu']);
    Route::get('/geocode', [ZomatoStubController::class, 'geocode']);
    Route::get('/cities', [ZomatoStubController::class, 'cities']);
    Route::get('/cuisines', [ZomatoStubController::class, 'cuisines']);
});
