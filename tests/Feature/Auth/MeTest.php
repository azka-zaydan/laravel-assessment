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

it('returns a JSON 401 even when client sends no Accept header', function () {
    // Regression: without shouldRenderJsonWhen() for api/*, Laravel's
    // Authenticate middleware tried to redirect to route('login') — which
    // doesn't exist in this API-only app — producing a 500 with an HTML
    // error page. A plain browser GET (no Accept: application/json) was
    // enough to trigger it. The JSON-accept happy path was masked by the
    // other test above using getJson().
    $this->get('/api/me')
        ->assertStatus(401)
        ->assertHeader('Content-Type', 'application/json')
        ->assertJson(['message' => 'Unauthenticated.']);
});

it('returns a JSON 401 when an invalid bearer token is sent', function () {
    $this->withToken('not-a-real-token')
        ->get('/api/me')
        ->assertStatus(401)
        ->assertHeader('Content-Type', 'application/json');
});
