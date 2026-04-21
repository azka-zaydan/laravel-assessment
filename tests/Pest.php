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

/**
 * Read the raw contents of a Zomato fixture file.
 *
 * Note: Pest 4 ships its own fixture() that returns a path.
 * This helper returns the file contents instead.
 *
 * @throws RuntimeException if the file does not exist
 */
function zomatoFixture(string $name): string
{
    $path = base_path('tests/Fixtures/zomato/'.$name);

    if (! file_exists($path)) {
        throw new RuntimeException("Zomato fixture not found: {$path}");
    }

    return file_get_contents($path);
}

/**
 * Create a user with 2FA fully enabled + confirmed within the last 24 hours,
 * issue a Passport personal access token, and set the Authorization header
 * on the current test client. Returns the created user.
 *
 * Uses a real Passport token + withToken() so that the custom PassportTokenGuard
 * (which resets $this->user on each setRequest call) resolves the user correctly.
 */
function actAsConfirmedUser(): User
{
    $user = User::factory()->withTwoFactor()->create();

    // Ensure two_factor_confirmed_at is recent (within the 24-hour TTL window)
    $user->forceFill(['two_factor_confirmed_at' => now()])->save();

    $token = $user->createToken('test')->accessToken;

    test()->withToken($token);

    return $user;
}
