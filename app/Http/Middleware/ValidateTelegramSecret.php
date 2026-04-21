<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateTelegramSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('X-Telegram-Bot-Api-Secret-Token', '');
        $expected = (string) config('services.telegram.webhook_secret');

        if (! hash_equals($expected, (string) $header)) {
            return response()->json(['error' => 'invalid secret'], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
