<?php

use App\Models\User;
use Illuminate\Support\Str;

it('returns 403 for non-admin authenticated user', function () {
    $user = User::factory()->create(['password' => bcrypt('Password1!'), 'is_admin' => false]);

    $loginResponse = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'Password1!',
    ]);

    $accessToken = $loginResponse->json('access_token');

    $this->withToken($accessToken)
        ->getJson('/api/admin/api-logs')
        ->assertStatus(403);
});

it('returns 403 for admin user with 2FA enabled but not confirmed (require_2fa middleware)', function () {
    // Create admin user with 2FA enabled but two_factor_confirmed_at = null
    $user = User::factory()->create([
        'password' => bcrypt('Password1!'),
        'is_admin' => true,
        'two_factor_enabled' => true,
        'two_factor_confirmed_at' => null,
        'two_factor_secret' => Str::random(16),
    ]);

    // Log in (will return challenge_token since 2FA is enabled)
    $loginResponse = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'Password1!',
    ]);

    // The user has 2FA enabled — login returns a challenge token
    // We need a Passport token but without completing 2FA verify
    // Use actingAs to create a token without verify, simulating missing two_factor_confirmed_at
    $token = $user->createToken('test-token')->accessToken;

    $this->withToken($token)
        ->getJson('/api/admin/api-logs')
        ->assertStatus(403)
        ->assertJsonPath('error', '2FA verification required');
});

it('returns 200 with { data: [] } for admin user with 2FA confirmed', function () {
    $user = User::factory()->withTwoFactor()->create([
        'password' => bcrypt('Password1!'),
        'is_admin' => true,
    ]);

    $loginResponse = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'Password1!',
    ]);

    $challengeToken = $loginResponse->json('challenge_token');
    $secret = $user->two_factor_secret;

    $verifyResponse = $this->postJson('/api/2fa/verify', [
        'challenge_token' => $challengeToken,
        'code' => otpFor($secret),
    ]);

    $accessToken = $verifyResponse->json('access_token');

    $this->withToken($accessToken)
        ->getJson('/api/admin/api-logs')
        ->assertStatus(200)
        ->assertJsonStructure(['data', 'links', 'meta'])
        ->assertJsonIsArray('data');
});
