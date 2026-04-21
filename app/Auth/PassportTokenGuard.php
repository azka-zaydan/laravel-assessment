<?php

namespace App\Auth;

use Illuminate\Http\Request;
use Laravel\Passport\Guards\TokenGuard;

/**
 * Extends Passport's TokenGuard to reset the cached user when the request is refreshed.
 * This ensures that token revocation is respected even within a single test run,
 * where multiple simulated HTTP requests share the same application instance.
 */
class PassportTokenGuard extends TokenGuard
{
    public function setRequest(Request $request): static
    {
        $this->user = null;

        return parent::setRequest($request);
    }
}
