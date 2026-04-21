<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorService
{
    private const RECOVERY_CODE_COUNT = 8;

    public function __construct(private readonly Google2FA $google2fa) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    public function buildOtpauthUrl(User $user, string $secret): string
    {
        $appName = config('app.name', 'Culinary Bot');

        return $this->google2fa->getQRCodeUrl(
            (string) $appName,
            $user->email,
            $secret,
        );
    }

    public function maskSecret(string $secret): string
    {
        $len = strlen($secret);
        $visibleChars = (int) max(4, (int) ceil($len * 0.25));

        return str_repeat('*', $len - $visibleChars).substr($secret, -$visibleChars);
    }

    public function verifyTotp(string $secret, string $code): bool
    {
        try {
            $result = $this->google2fa->verifyKey($secret, $code);

            return $result !== false;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array{plain: list<string>, hashed: list<string>}
     */
    public function generateRecoveryCodes(): array
    {
        /** @var list<string> $plain */
        $plain = [];
        /** @var list<string> $hashed */
        $hashed = [];

        for ($i = 0; $i < self::RECOVERY_CODE_COUNT; $i++) {
            $code = $this->generateRecoveryCode();
            $plain[] = $code;
            $hashed[] = Hash::make($code);
        }

        return ['plain' => $plain, 'hashed' => $hashed];
    }

    private function generateRecoveryCode(): string
    {
        $bytes = bin2hex(random_bytes(5)); // 10 hex chars

        return substr($bytes, 0, 4).'-'.substr($bytes, 4, 4);
    }

    /**
     * Try to consume a recovery code. Returns the updated hashes array on success, null on failure.
     *
     * @param  list<string>  $hashedCodes
     * @return list<string>|null
     */
    public function consumeRecoveryCode(string $plain, array $hashedCodes): ?array
    {
        foreach ($hashedCodes as $index => $hash) {
            if (Hash::check($plain, $hash)) {
                unset($hashedCodes[$index]);

                return array_values($hashedCodes);
            }
        }

        return null;
    }

    /**
     * Verify either a TOTP code or a recovery code.
     * Returns 'totp', 'recovery', or null.
     *
     * @param  list<string>  $hashedRecoveryCodes
     * @return array{method: 'totp'|'recovery', remaining_codes?: list<string>}|null
     */
    public function verify(User $user, string $code, array $hashedRecoveryCodes): ?array
    {
        // Try TOTP first
        if ($user->two_factor_secret !== null && $this->verifyTotp((string) $user->two_factor_secret, $code)) {
            return ['method' => 'totp'];
        }

        // Fall back to recovery code
        $remaining = $this->consumeRecoveryCode($code, $hashedRecoveryCodes);
        if ($remaining !== null) {
            return ['method' => 'recovery', 'remaining_codes' => $remaining];
        }

        return null;
    }
}
