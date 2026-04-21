<?php

use App\Models\User;
use Firebase\JWT\JWT;

/**
 * Helper: create a 2FA-enabled user, log in, return [user, challengeToken, secret].
 */
function loginTwoFactorUser(): array
{
    $user = User::factory()->withTwoFactor()->create(['password' => bcrypt('Password1!')]);

    $loginResponse = test()->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'Password1!',
    ]);

    $challengeToken = $loginResponse->json('challenge_token');
    $secret = $user->two_factor_secret;

    return [$user, $challengeToken, $secret];
}

it('issues access_token and refresh cookie with valid challenge_token and valid TOTP', function () {
    [$user, $challengeToken, $secret] = loginTwoFactorUser();

    $response = $this->postJson('/api/2fa/verify', [
        'challenge_token' => $challengeToken,
        'code' => otpFor($secret),
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['access_token', 'token_type', 'expires_in', 'user']);

    verifyCookie($response, 'refresh_token');
});

it('sets two_factor_confirmed_at after successful verification', function () {
    [$user, $challengeToken, $secret] = loginTwoFactorUser();

    $this->postJson('/api/2fa/verify', [
        'challenge_token' => $challengeToken,
        'code' => otpFor($secret),
    ])->assertStatus(200);

    $user->refresh();
    expect($user->two_factor_confirmed_at)->not->toBeNull();
});

it('accepts a valid recovery code and consumes it so it cannot be used again', function () {
    $user = User::factory()->withTwoFactor()->create(['password' => bcrypt('Password1!')]);

    $loginResponse = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'Password1!',
    ]);

    $challengeToken = $loginResponse->json('challenge_token');

    // Get the raw recovery codes from factory — we need the plaintext
    // The withTwoFactor factory state stores hashed codes; we need to regenerate
    // Trigger regeneration via the confirm flow isn't possible here since 2FA is already enabled.
    // Instead, manually set a known plaintext code on the user for this test.
    $knownCode = 'AAAA-BBBB-CCCC';
    $user->two_factor_recovery_codes = array_merge(
        array_map('bcrypt', [$knownCode]),
        array_slice($user->two_factor_recovery_codes ?? [], 0, 7)
    );
    $user->save();

    $response = $this->postJson('/api/2fa/verify', [
        'challenge_token' => $challengeToken,
        'code' => $knownCode,
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['access_token']);

    // The used code should be consumed; attempt a second login + verify with the same code
    $loginResponse2 = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'Password1!',
    ]);

    $challengeToken2 = $loginResponse2->json('challenge_token');

    $this->postJson('/api/2fa/verify', [
        'challenge_token' => $challengeToken2,
        'code' => $knownCode,
    ])->assertStatus(401);
});

it('returns 401 for an expired challenge_token', function () {
    [$user, $challengeToken, $secret] = loginTwoFactorUser();

    // Build a challenge token that is already expired (exp = 5 minutes ago).
    // Laravel's travel() only fakes Carbon::now() — not PHP's time() which is
    // what firebase/php-jwt uses. We therefore forge an expired JWT directly.
    $jwtSecret = config('app.jwt_challenge_secret');
    $now = time();
    $expiredPayload = [
        'iss' => config('app.url'),
        'sub' => (string) $user->id,
        'iat' => $now - 360,   // 6 minutes ago
        'exp' => $now - 60,    // expired 1 minute ago
        'purpose' => '2fa_challenge',
    ];
    $expiredToken = JWT::encode($expiredPayload, $jwtSecret, 'HS256');

    $this->postJson('/api/2fa/verify', [
        'challenge_token' => $expiredToken,
        'code' => otpFor($secret),
    ])->assertStatus(401);
});

it('returns 401 for a tampered challenge_token with bad signature', function () {
    [$user, $challengeToken, $secret] = loginTwoFactorUser();

    // Tamper: append garbage to the token
    $tampered = $challengeToken.'TAMPERED';

    $this->postJson('/api/2fa/verify', [
        'challenge_token' => $tampered,
        'code' => otpFor($secret),
    ])->assertStatus(401);
});

it('returns 401 for a wrong TOTP code with valid challenge_token', function () {
    [$user, $challengeToken, $secret] = loginTwoFactorUser();

    $this->postJson('/api/2fa/verify', [
        'challenge_token' => $challengeToken,
        'code' => '000000',
    ])->assertStatus(401);
});
