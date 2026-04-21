<?php

use App\Models\User;

it('revokes current token so subsequent /api/me returns 401', function () {
    $user = User::factory()->create(['password' => bcrypt('Password1!')]);

    $loginResponse = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'Password1!',
    ]);

    $accessToken = $loginResponse->json('access_token');

    $this->withToken($accessToken)
        ->postJson('/api/logout')
        ->assertStatus(204);

    $this->withToken($accessToken)
        ->getJson('/api/me')
        ->assertStatus(401);
});

it('returns 401 when logging out without authentication', function () {
    $this->postJson('/api/logout')
        ->assertStatus(401);
});
