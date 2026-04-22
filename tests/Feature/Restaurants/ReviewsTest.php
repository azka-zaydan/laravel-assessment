<?php

use App\Models\User;

beforeEach(function () {
    config(['services.restaurants.provider' => 'fixture']);
});

it('returns 3 reviews with normalized shape for restaurant 16507621', function () {
    actAsConfirmedUser();

    $response = $this->getJson('/api/restaurants/16507621/reviews');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id', 'rating', 'review_text',
                    'user' => ['name', 'thumb_url'],
                    'created_at',
                ],
            ],
            'meta' => ['total', 'start', 'count'],
        ]);

    expect($response->json('data'))->toHaveCount(3);
});

it('review items have correct field types', function () {
    actAsConfirmedUser();

    $response = $this->getJson('/api/restaurants/16507621/reviews');
    $response->assertStatus(200);

    $review = $response->json('data.0');
    expect($review['id'])->toBeInt();
    expect($review['rating'])->toBeNumeric();
    expect($review['review_text'])->toBeString();
    expect($review['user']['name'])->toBeString();
    expect($review['created_at'])->toBeString();
});

it('meta reflects pagination params start and count', function () {
    actAsConfirmedUser();

    $response = $this->getJson('/api/restaurants/16507621/reviews?start=0&count=3');
    $response->assertStatus(200);

    expect($response->json('meta.start'))->toBe(0);
    expect($response->json('meta.count'))->toBeInt();
});

it('returns 401 when unauthenticated', function () {
    $this->getJson('/api/restaurants/16507621/reviews')
        ->assertStatus(401);
});

it('returns 403 when 2FA enabled but not confirmed', function () {
    $user = User::factory()->withTwoFactor()->create([
        'two_factor_confirmed_at' => null,
    ]);

    $token = $user->createToken('test')->accessToken;

    $this->withToken($token)
        ->getJson('/api/restaurants/16507621/reviews')
        ->assertStatus(403);
});

it('returns 404 for a non-existent restaurant id', function () {
    actAsConfirmedUser();

    $this->getJson('/api/restaurants/999999/reviews')
        ->assertStatus(404)
        ->assertJsonStructure(['error']);
});
