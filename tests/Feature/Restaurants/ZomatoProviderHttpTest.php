<?php

use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Switch provider to zomato so ZomatoProvider is used
    config([
        'services.restaurants.provider' => 'zomato',
        'services.restaurants.zomato.key' => 'test-zomato-key',
        'services.restaurants.zomato.base_url' => 'https://developers.zomato.com/api/v2.1',
    ]);
});

it('sends user-key and Accept headers when calling /search', function () {
    Http::fake([
        'developers.zomato.com/api/v2.1/search*' => Http::response(
            json_decode(zomatoFixture('search_pizza.json'), true),
            200
        ),
    ]);

    actAsConfirmedUser();

    $response = $this->getJson('/api/restaurants?q=pizza');

    // ZomatoProvider is now a permanent provider (prod deploy flipped to it
    // multiple times), so the endpoint MUST exist. A 404 here is a real
    // routing regression, not a "provider not yet wired" case.
    $response->assertStatus(200);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'developers.zomato.com/api/v2.1/search')
            && $request->hasHeader('user-key')
            && $request->hasHeader('Accept', 'application/json');
    });
});

it('normalized response matches expected shape from zomato fixture', function () {
    Http::fake([
        'developers.zomato.com/api/v2.1/search*' => Http::response(
            json_decode(zomatoFixture('search_pizza.json'), true),
            200
        ),
    ]);

    actAsConfirmedUser();

    $response = $this->getJson('/api/restaurants?q=pizza');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'address', 'rating', 'cuisines', 'location', 'thumb_url'],
            ],
            'meta' => ['total', 'start', 'count'],
        ]);
});

it('sends user-key header when calling /restaurant endpoint', function () {
    Http::fake([
        'developers.zomato.com/api/v2.1/restaurant*' => Http::response(
            json_decode(zomatoFixture('restaurant_16507621.json'), true),
            200
        ),
    ]);

    actAsConfirmedUser();

    $response = $this->getJson('/api/restaurants/16507621');

    $response->assertStatus(200);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'developers.zomato.com/api/v2.1/restaurant')
            && $request->hasHeader('user-key');
    });
});
