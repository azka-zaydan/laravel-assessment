<?php

namespace App\Services\Auth;

final class RecoveryVerified extends VerifyResult
{
    /**
     * @param  list<string>  $remainingCodes
     */
    public function __construct(
        public readonly array $remainingCodes,
    ) {}
}
