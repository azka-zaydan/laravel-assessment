<?php

use App\Models\User;

it('returns the authenticated user data', function () {
    $user = User::factory()->create(['password' => bcrypt('Password1!')]);

    $loginResponse = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'Password1!',
    ]);

    $accessToken = $loginResponse->json('access_token');

    $this->withToken($accessToken)
        ->getJson('/api/me')
        ->assertStatus(200)
        ->assertJsonStructure(['user' => ['id', 'name', 'email']]);
});

it('returns 401 when not authenticated', function () {
    $this->getJson('/api/me')
        ->assertStatus(401);
});
