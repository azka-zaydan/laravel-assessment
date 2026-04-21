<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

it('returns access_token, refresh cookie, and user for 2FA-disabled user', function () {
    User::factory()->create([
        'email' => 'user@example.com',
        'password' => bcrypt('Password1!'),
        'two_factor_enabled' => false,
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'user@example.com',
        'password' => 'Password1!',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['access_token', 'token_type', 'expires_in', 'user'])
        ->assertJsonMissing(['challenge_token'])
        ->assertJsonMissing(['two_factor_required']);

    verifyCookie($response, 'refresh_token');
});

it('returns challenge_token and two_factor_required=true for 2FA-enabled user without access_token or refresh cookie', function () {
    User::factory()->withTwoFactor()->create([
        'email' => 'twofa@example.com',
        'password' => bcrypt('Password1!'),
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'twofa@example.com',
        'password' => 'Password1!',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['challenge_token', 'two_factor_required', 'expires_in'])
        ->assertJsonPath('two_factor_required', true)
        ->assertJsonMissing(['access_token']);

    // No refresh cookie should be set
    expect($response->headers->getCookies())->toBeArray();
    $cookieNames = array_map(fn ($c) => $c->getName(), $response->headers->getCookies());
    expect($cookieNames)->not->toContain('refresh_token');
});

it('returns 401 for wrong password', function () {
    User::factory()->create([
        'email' => 'user@example.com',
        'password' => bcrypt('Password1!'),
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'user@example.com',
        'password' => 'WrongPassword!',
    ]);

    $response->assertStatus(401)
        ->assertJsonStructure(['error']);
});

it('returns 401 for nonexistent email', function () {
    $response = $this->postJson('/api/login', [
        'email' => 'nobody@example.com',
        'password' => 'Password1!',
    ]);

    $response->assertStatus(401)
        ->assertJsonStructure(['error']);
});

it('throttles after 6 attempts within 1 minute and returns 429 on the 7th', function () {
    User::factory()->create([
        'email' => 'throttle@example.com',
        'password' => bcrypt('Password1!'),
    ]);

    // Clear any existing rate limits
    RateLimiter::clear('login');

    for ($i = 0; $i < 6; $i++) {
        $this->postJson('/api/login', [
            'email' => 'throttle@example.com',
            'password' => 'WrongPassword!',
        ]);
    }

    $response = $this->postJson('/api/login', [
        'email' => 'throttle@example.com',
        'password' => 'WrongPassword!',
    ]);

    $response->assertStatus(429);
});
