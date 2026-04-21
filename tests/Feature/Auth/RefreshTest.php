<?php

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Testing\TestResponse;

/**
 * Extract the raw refresh token cookie value from a test response.
 *
 * The API middleware group does NOT include EncryptCookies, so the cookie
 * value on the wire is the plaintext 64-char hex string minted by
 * RefreshTokenService::mint().
 */
function getRefreshTokenFromResponse(TestResponse $response): ?string
{
    foreach ($response->headers->getCookies() as $cookie) {
        if ($cookie->getName() === 'refresh_token') {
            return $cookie->getValue();
        }
    }

    return null;
}

it('returns a new access_token given a valid refresh cookie', function () {
    $user = User::factory()->create(['password' => bcrypt('Password1!')]);

    $loginResponse = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'Password1!',
    ]);

    $refreshToken = getRefreshTokenFromResponse($loginResponse);
    expect($refreshToken)->not->toBeNull();

    // withCredentials() includes cookies in the JSON request (simulates browser behaviour)
    $this->withCredentials()
        ->withUnencryptedCookie('refresh_token', $refreshToken)
        ->postJson('/api/auth/refresh')
        ->assertStatus(200)
        ->assertJsonStructure(['access_token', 'token_type', 'expires_in']);
});

it('returns 401 when refresh cookie is missing', function () {
    $this->postJson('/api/auth/refresh')
        ->assertStatus(401);
});

it('returns 401 when using a revoked refresh token', function () {
    $user = User::factory()->create(['password' => bcrypt('Password1!')]);

    $loginResponse = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'Password1!',
    ]);

    $accessToken = $loginResponse->json('access_token');
    $refreshToken = getRefreshTokenFromResponse($loginResponse);
    expect($refreshToken)->not->toBeNull();

    // Revoke via logout — the backend should also revoke the refresh token on logout
    $this->withToken($accessToken)->postJson('/api/logout')->assertStatus(204);

    // Revoke the refresh token directly in DB to simulate what logout *should* do
    // (the backend logout currently only revokes the Passport access token)
    RefreshToken::where('user_id', $user->id)->update(['revoked_at' => now()]);

    // Refresh should now return 401 because the token is revoked
    $this->withCredentials()
        ->withUnencryptedCookie('refresh_token', $refreshToken)
        ->postJson('/api/auth/refresh')
        ->assertStatus(401);
});
