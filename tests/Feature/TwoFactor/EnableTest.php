<?php

use App\Models\User;

it('returns otpauth_url and secret_masked, stores encrypted secret, does not flip two_factor_enabled', function () {
    $user = User::factory()->create(['password' => bcrypt('Password1!')]);

    $loginResponse = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'Password1!',
    ]);

    $accessToken = $loginResponse->json('access_token');

    $response = $this->withToken($accessToken)
        ->postJson('/api/2fa/enable', ['password' => 'Password1!']);

    $response->assertStatus(200)
        ->assertJsonStructure(['otpauth_url', 'secret_masked']);

    $user->refresh();
    expect($user->two_factor_secret)->not->toBeNull();
    expect($user->two_factor_enabled)->toBeFalse();
});

it('returns 422 when password re-confirm is wrong', function () {
    $user = User::factory()->create(['password' => bcrypt('Password1!')]);

    $loginResponse = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'Password1!',
    ]);

    $accessToken = $loginResponse->json('access_token');

    $this->withToken($accessToken)
        ->postJson('/api/2fa/enable', ['password' => 'WrongPassword!'])
        ->assertStatus(422);
});

it('returns 403 when 2FA is already enabled', function () {
    $user = User::factory()->withTwoFactor()->create(['password' => bcrypt('Password1!')]);

    $loginResponse = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'Password1!',
    ]);

    // For 2FA-enabled users login returns a challenge_token; we need to verify to get access token
    $challengeToken = $loginResponse->json('challenge_token');

    $secret = $user->two_factor_secret;
    $otp = otpFor($secret);

    $verifyResponse = $this->postJson('/api/2fa/verify', [
        'challenge_token' => $challengeToken,
        'code' => $otp,
    ]);

    $accessToken = $verifyResponse->json('access_token');

    $this->withToken($accessToken)
        ->postJson('/api/2fa/enable', ['password' => 'Password1!'])
        ->assertStatus(403);
});
