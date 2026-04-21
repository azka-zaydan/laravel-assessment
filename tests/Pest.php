<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * Generate the current TOTP code for a given secret.
 */
function otpFor(string $secret): string
{
    return (new Google2FA)->getCurrentOtp($secret);
}

/**
 * Register a new user and log them in, returning [$user, $accessToken].
 */
function registerAndLogin(array $overrides = []): array
{
    $data = array_merge([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ], $overrides);

    $response = test()->postJson('/api/register', $data);
    $user = User::where('email', $data['email'])->firstOrFail();
    $accessToken = $response->json('access_token');

    return [$user, $accessToken];
}

/**
 * Assert that a cookie is present on the response.
 */
function verifyCookie(TestResponse $response, string $name): void
{
    $response->assertCookie($name);
}
