<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ZomatoRateLimiter
{
    /**
     * Maximum number of Zomato API calls allowed per day.
     */
    private const DAILY_LIMIT = 1000;

    /**
     * Attempt to consume one API call from the daily quota.
     *
     * Returns true if the call is allowed, false if the limit is exceeded
     * (fail-open: logs a warning instead of throwing, so callers can
     * gracefully fall back to cache/mock).
     */
    public function attempt(): bool
    {
        $key = 'zomato:daily:'.date('Y-m-d');
        $count = (int) Cache::increment($key);

        // Ensure the key expires at midnight + a small buffer so it auto-resets
        if ($count === 1) {
            // First increment today — set TTL to 25 hours so it survives any
            // clock skew / timezone drift and is cleaned up automatically.
            Cache::put($key, 1, now()->addHours(25));
        }

        if ($count > self::DAILY_LIMIT) {
            Log::warning('Zomato daily rate limit exceeded', [
                'key' => $key,
                'count' => $count,
                'limit' => self::DAILY_LIMIT,
            ]);

            return false;
        }

        return true;
    }

    /**
     * Return the current daily call count (for diagnostics).
     */
    public function count(): int
    {
        $key = 'zomato:daily:'.date('Y-m-d');

        return (int) Cache::get($key, 0);
    }
}
