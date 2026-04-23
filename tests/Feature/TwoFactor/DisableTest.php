<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Helper: create a 2FA-confirmed user, complete the verify flow, return [user, accessToken].
 * Mirrors the helper in RecoveryCodesTest so the same preconditions apply (session has
 * passed through /api/2fa/verify, so require_2fa middleware accepts the request).
 */
function loginConfirmedTwoFactorUserForDisable(): array
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

it('disables 2FA with valid password and TOTP code, nulling all 2FA columns', function () {
    [$user, $accessToken] = loginConfirmedTwoFactorUserForDisable();

    $response = $this->withToken($accessToken)
        ->postJson('/api/2fa/disable', [
            'password' => 'Password1!',
            'code' => otpFor($user->two_factor_secret),
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('message', '2FA has been disabled.');

    $user->refresh();

    expect($user->two_factor_enabled)->toBeFalse();
    expect($user->two_factor_secret)->toBeNull();
    expect($user->two_factor_recovery_codes)->toBeNull();
    expect($user->two_factor_confirmed_at)->toBeNull();
});

it('disables 2FA with valid password and a recovery code', function () {
    [$user, $accessToken] = loginConfirmedTwoFactorUserForDisable();

    // Inject a known recovery code hash we can present in plaintext.
    $knownPlain = 'ZZZZ-ZZZZ';
    $existing = is_array($user->two_factor_recovery_codes) ? $user->two_factor_recovery_codes : [];
    $user->two_factor_recovery_codes = array_merge([Hash::make($knownPlain)], $existing);
    $user->save();

    $response = $this->withToken($accessToken)
        ->postJson('/api/2fa/disable', [
            'password' => 'Password1!',
            'code' => $knownPlain,
        ]);

    $response->assertStatus(200);

    $user->refresh();
    expect($user->two_factor_enabled)->toBeFalse();
    expect($user->two_factor_secret)->toBeNull();
});

it('returns 422 when the password is wrong', function () {
    [$user, $accessToken] = loginConfirmedTwoFactorUserForDisable();

    $this->withToken($accessToken)
        ->postJson('/api/2fa/disable', [
            'password' => 'WrongPassword!',
            'code' => otpFor($user->two_factor_secret),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['password']);

    $user->refresh();
    expect($user->two_factor_enabled)->toBeTrue();
});

it('returns 422 when the TOTP code is invalid', function () {
    [$user, $accessToken] = loginConfirmedTwoFactorUserForDisable();

    $this->withToken($accessToken)
        ->postJson('/api/2fa/disable', [
            'password' => 'Password1!',
            'code' => '000000',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['code']);

    $user->refresh();
    expect($user->two_factor_enabled)->toBeTrue();
});

it('returns 403 when 2FA is not enabled on the account', function () {
    // Plain user, no 2FA. Passport token is enough for auth:api, but the
    // require_2fa middleware lets the request through (because 2FA is off)
    // and the controller itself responds with 403.
    $user = User::factory()->create(['password' => bcrypt('Password1!')]);
    $token = $user->createToken('test-token')->accessToken;

    $this->withToken($token)
        ->postJson('/api/2fa/disable', [
            'password' => 'Password1!',
            'code' => '123456',
        ])
        ->assertStatus(403)
        ->assertJsonPath('error', '2FA is not enabled on this account.');
});

it('returns 403 via require_2fa when 2FA is enabled but session is unverified', function () {
    // Mirrors the analogous test in RecoveryCodesTest — the disable endpoint
    // lives behind `require_2fa`, so an unverified session must be blocked
    // before the controller runs.
    $user = User::factory()->create([
        'password' => bcrypt('Password1!'),
        'two_factor_enabled' => true,
        'two_factor_confirmed_at' => null,
        'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
    ]);

    $token = $user->createToken('test-token')->accessToken;

    $this->withToken($token)
        ->postJson('/api/2fa/disable', [
            'password' => 'Password1!',
            'code' => '123456',
        ])
        ->assertStatus(403)
        ->assertJsonPath('error', '2FA verification required');
});
