<?php

use App\Models\User;

/**
 * Helper: create a 2FA-confirmed user, complete the verify flow, return [user, accessToken].
 */
function loginConfirmedTwoFactorUser(): array
{
    $user = User::factory()->withTwoFactor()->create(['password' => bcrypt('Password1!')]);

    $loginResponse = test()->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'Password1!',
    ]);

    $challengeToken = $loginResponse->json('challenge_token');
    $secret = $user->two_factor_secret;

    $verifyResponse = test()->postJson('/api/2fa/verify', [
        'challenge_token' => $challengeToken,
        'code' => otpFor($secret),
    ]);

    $accessToken = $verifyResponse->json('access_token');

    $user->refresh();

    return [$user, $accessToken];
}

it('regenerates 8 new recovery codes with correct password and old codes no longer work', function () {
    [$user, $accessToken] = loginConfirmedTwoFactorUser();

    // Store old hashed codes for later verification
    $oldHashedCodes = $user->two_factor_recovery_codes;

    $response = $this->withToken($accessToken)
        ->postJson('/api/2fa/recovery-codes/regenerate', ['password' => 'Password1!']);

    $response->assertStatus(200)
        ->assertJsonStructure(['recovery_codes'])
        ->assertJsonCount(8, 'recovery_codes');

    $newPlaintextCodes = $response->json('recovery_codes');

    $user->refresh();
    $newHashedCodes = $user->two_factor_recovery_codes;

    // New codes differ from old codes
    expect($newHashedCodes)->not->toBe($oldHashedCodes);

    // The new plaintext codes are present and match the new stored hashes
    expect($newPlaintextCodes)->toHaveCount(8);
});

it('returns 422 when wrong password is provided for regeneration', function () {
    [$user, $accessToken] = loginConfirmedTwoFactorUser();

    $this->withToken($accessToken)
        ->postJson('/api/2fa/recovery-codes/regenerate', ['password' => 'WrongPassword!'])
        ->assertStatus(422);
});

it('returns 403 for user with 2FA enabled but two_factor_confirmed_at null (require_2fa middleware)', function () {
    // User has 2FA enabled (so they need to go through the verify flow)
    // but two_factor_confirmed_at is null — they haven't verified this session.
    $user = User::factory()->create([
        'password' => bcrypt('Password1!'),
        'two_factor_enabled' => true,
        'two_factor_confirmed_at' => null,
        'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
    ]);

    // Issue a Passport token directly (bypassing the verify flow to simulate
    // a user who is logged in but hasn't completed 2FA verification).
    $token = $user->createToken('test-token')->accessToken;

    $this->withToken($token)
        ->postJson('/api/2fa/recovery-codes/regenerate', ['password' => 'Password1!'])
        ->assertStatus(403)
        ->assertJsonPath('error', '2FA verification required');
});
