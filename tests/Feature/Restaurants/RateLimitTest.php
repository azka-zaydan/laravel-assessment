<?php

use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    config(['services.restaurants.provider' => 'mock']);
});

it('does not throw when zomato daily rate limit counter exceeds 1000', function () {
    // Pre-set the daily counter above the 1000 cap
    $dailyKey = 'zomato:daily:'.today()->toDateString();
    Cache::put($dailyKey, 1001, now()->endOfDay());

    actAsConfirmedUser();

    // Should fail-open: return a valid response (possibly empty or cached) — not an exception/500
    $response = $this->getJson('/api/restaurants?q=pizza');

    expect($response->status())->not->toBe(500);

    // May return 200 with data (from Postgres mirror / mock fallback) or 200 empty
    // Either is acceptable — the key requirement is no unhandled exception
    if ($response->status() === 200) {
        $response->assertJsonStructure(['data', 'meta']);
    } elseif ($response->status() === 429) {
        // Explicitly returning 429 is also acceptable fail-open behavior
        expect(true)->toBeTrue();
    } else {
        expect($response->status())->toBeLessThan(500);
    }
});

it('returns valid response shape when rate limit key is at exactly the threshold', function () {
    $dailyKey = 'zomato:daily:'.today()->toDateString();
    Cache::put($dailyKey, 1000, now()->endOfDay());

    actAsConfirmedUser();

    $response = $this->getJson('/api/restaurants?q=pizza');

    // With mock provider, this should still succeed normally
    $response->assertStatus(200)
        ->assertJsonStructure(['data', 'meta']);
});
