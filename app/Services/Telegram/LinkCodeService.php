<?php

namespace App\Services\Telegram;

use App\Models\User;
use Illuminate\Support\Facades\Redis;

class LinkCodeService
{
    private const TTL_SECONDS = 600;

    private const KEY_PREFIX = 'telegram:link:';

    public function generate(User $user): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Redis::setex(self::KEY_PREFIX.$code, self::TTL_SECONDS, (string) $user->id);

        return $code;
    }

    public function consume(string $code): ?int
    {
        $key = self::KEY_PREFIX.$code;
        $userId = Redis::get($key);

        if ($userId === null || $userId === false) {
            return null;
        }

        Redis::del($key);

        return (int) $userId;
    }
}
