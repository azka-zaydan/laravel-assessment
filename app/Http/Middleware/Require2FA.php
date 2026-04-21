<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class Require2FA
{
    private const CONFIRMATION_TTL_HOURS = 24;

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return response()->json(['error' => 'Unauthenticated.'], Response::HTTP_UNAUTHORIZED);
        }

        if (! $user->two_factor_enabled) {
            return $next($request);
        }

        $confirmedAt = $user->two_factor_confirmed_at;

        if (! ($confirmedAt instanceof Carbon)) {
            return response()->json(
                ['error' => '2FA verification required'],
                Response::HTTP_FORBIDDEN,
            );
        }

        if ($confirmedAt->lt(now()->subHours(self::CONFIRMATION_TTL_HOURS))) {
            return response()->json(
                ['error' => '2FA verification required'],
                Response::HTTP_FORBIDDEN,
            );
        }

        return $next($request);
    }
}
