<?php

beforeEach(function () {
    config(['services.restaurants.provider' => 'mock']);
});

it('returns 4 menu dishes for restaurant 16507621', function () {
    actAsConfirmedUser();

    $response = $this->getJson('/api/restaurants/16507621/menu');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => ['name', 'price'],
            ],
        ]);

    expect($response->json('data'))->toHaveCount(4);
});

it('each menu item has name and price fields', function () {
    actAsConfirmedUser();

    $response = $this->getJson('/api/restaurants/16507621/menu');
    $response->assertStatus(200);

    foreach ($response->json('data') as $item) {
        expect($item['name'])->toBeString()->not->toBeEmpty();
        expect($item['price'])->toBeString()->not->toBeEmpty();
    }
});

it('returns 401 when unauthenticated', function () {
    $this->getJson('/api/restaurants/16507621/menu')
        ->assertStatus(401);
});
