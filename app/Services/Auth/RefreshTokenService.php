<?php

namespace App\Services\Auth;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Http\Request;

class RefreshTokenService
{
    private const TTL_DAYS = 30;

    private const TOKEN_LENGTH = 64;

    public function mint(User $user, Request $request): string
    {
        $plain = bin2hex(random_bytes(self::TOKEN_LENGTH / 2));
        $hash = hash('sha256', $plain);

        RefreshToken::create([
            'user_id' => $user->id,
            'token_hash' => $hash,
            'expires_at' => now()->addDays(self::TTL_DAYS),
            'user_agent' => $request->userAgent(),
            'ip' => $request->ip(),
        ]);

        return $plain;
    }

    public function verify(string $plain): ?RefreshToken
    {
        $hash = hash('sha256', $plain);

        $token = RefreshToken::where('token_hash', $hash)->first();

        if ($token === null || ! $token->isValid()) {
            return null;
        }

        return $token;
    }

    public function rotate(RefreshToken $old, Request $request): string
    {
        $old->revoke();

        /** @var User $user */
        $user = $old->user;

        return $this->mint($user, $request);
    }

    /**
     * @return array{max_age: int, path: string, same_site: string, http_only: bool, secure: bool}
     */
    public function cookieOptions(): array
    {
        return [
            'max_age' => self::TTL_DAYS * 24 * 3600,
            'path' => '/',
            'same_site' => 'Strict',
            'http_only' => true,
            'secure' => app()->environment('production'),
        ];
    }

    public function ttlDays(): int
    {
        return self::TTL_DAYS;
    }
}
