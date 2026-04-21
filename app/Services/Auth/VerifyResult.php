<?php

namespace App\Services\Auth;

/**
 * Sealed result of TwoFactorService::verify — either a TotpVerified or a
 * RecoveryVerified, never both. Having separate classes lets the controller
 * branch on `instanceof` without runtime type assertions.
 */
abstract class VerifyResult
{
    public function method(): string
    {
        return match (true) {
            $this instanceof TotpVerified => 'totp',
            $this instanceof RecoveryVerified => 'recovery',
            default => 'unknown',
        };
    }
}
