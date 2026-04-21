<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * The most recently generated 2FA secret (for test reuse).
     */
    public static ?string $lastTwoFactorSecret = null;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'is_admin' => false,
            'two_factor_enabled' => false,
            'two_factor_confirmed_at' => null,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user is an administrator.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_admin' => true,
        ]);
    }

    /**
     * Set up the user with 2FA fully enabled and confirmed.
     *
     * Generates a real TOTP secret and 8 bcrypt-hashed recovery codes.
     * The plaintext secret is stored in UserFactory::$lastTwoFactorSecret for test reuse.
     */
    public function withTwoFactor(): static
    {
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey();

        // Store for test reuse
        static::$lastTwoFactorSecret = $secret;

        $plainCodes = [];
        for ($i = 0; $i < 8; $i++) {
            $plainCodes[] = strtoupper(Str::random(4).'-'.Str::random(4).'-'.Str::random(4));
        }

        $hashedCodes = array_map(fn (string $code) => Hash::make($code), $plainCodes);

        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => $secret,
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $hashedCodes,
        ]);
    }
}
