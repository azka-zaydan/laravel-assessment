<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Helper: enable 2FA for a user and return [accessToken, secret].
 */
function setupTwoFactorEnable(User $user): array
{
    $loginResponse = test()->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'Password1!',
    ]);

    $accessToken = $loginResponse->json('access_token');

    $enableResponse = test()->withToken($accessToken)
        ->postJson('/api/2fa/enable', ['password' => 'Password1!']);

    $user->refresh();
    $secret = $user->two_factor_secret;

    return [$accessToken, $secret];
}

it('confirms 2FA with a valid TOTP code and returns 8 recovery codes', function () {
    $user = User::factory()->create(['password' => bcrypt('Password1!')]);

    [$accessToken, $secret] = setupTwoFactorEnable($user);

    $code = otpFor($secret);

    $response = $this->withToken($accessToken)
        ->postJson('/api/2fa/confirm', ['code' => $code]);

    $response->assertStatus(200)
        ->assertJsonStructure(['recovery_codes'])
        ->assertJsonCount(8, 'recovery_codes');
});

it('marks user as two_factor_enabled=true after confirm', function () {
    $user = User::factory()->create(['password' => bcrypt('Password1!')]);

    [$accessToken, $secret] = setupTwoFactorEnable($user);

    $code = otpFor($secret);

    $this->withToken($accessToken)
        ->postJson('/api/2fa/confirm', ['code' => $code])
        ->assertStatus(200);

    $user->refresh();
    expect($user->two_factor_enabled)->toBeTrue();
});

it('returns 422 for an invalid TOTP code', function () {
    $user = User::factory()->create(['password' => bcrypt('Password1!')]);

    [$accessToken] = setupTwoFactorEnable($user);

    $this->withToken($accessToken)
        ->postJson('/api/2fa/confirm', ['code' => '000000'])
        ->assertStatus(422);
});

it('rejects a second confirm after 2FA already enabled without rotating recovery codes', function () {
    $user = User::factory()->create(['password' => bcrypt('Password1!')]);

    [$accessToken, $secret] = setupTwoFactorEnable($user);

    $code = otpFor($secret);

    $firstResponse = $this->withToken($accessToken)
        ->postJson('/api/2fa/confirm', ['code' => $code])
        ->assertStatus(200);

    $firstCodes = $firstResponse->json('recovery_codes');

    $user->refresh();
    $storedBefore = $user->two_factor_recovery_codes;

    $this->withToken($accessToken)
        ->postJson('/api/2fa/confirm', ['code' => otpFor($secret)])
        ->assertStatus(403);

    $user->refresh();
    expect($user->two_factor_recovery_codes)->toBe($storedBefore);
    expect($firstCodes)->toBeArray()->toHaveCount(8);
});

it('returns plaintext recovery codes in response; stored codes in DB are bcrypt-hashed', function () {
    $user = User::factory()->create(['password' => bcrypt('Password1!')]);

    [$accessToken, $secret] = setupTwoFactorEnable($user);

    $code = otpFor($secret);

    $response = $this->withToken($accessToken)
        ->postJson('/api/2fa/confirm', ['code' => $code]);

    $plaintextCodes = $response->json('recovery_codes');

    $user->refresh();
    $storedCodes = $user->two_factor_recovery_codes;

    expect($storedCodes)->toBeArray()->toHaveCount(8);

    // Plaintext codes should NOT equal the stored hashes
    expect($plaintextCodes[0])->not->toBe($storedCodes[0]);

    // Hash::check should verify at least the first stored code against the plaintext
    $matched = false;
    foreach ($storedCodes as $stored) {
        if (Hash::check($plaintextCodes[0], $stored)) {
            $matched = true;
            break;
        }
    }
    expect($matched)->toBeTrue();
});
