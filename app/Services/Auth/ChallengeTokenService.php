<?php

namespace App\Services\Auth;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Throwable;

class ChallengeTokenService
{
    private const ALGORITHM = 'HS256';

    private function secret(): string
    {
        /** @var string $secret */
        $secret = config('app.jwt_challenge_secret');

        return $secret;
    }

    private function ttlMinutes(): int
    {
        /** @var int|string $ttl */
        $ttl = config('app.jwt_challenge_ttl_minutes', 5);

        return (int) $ttl;
    }

    public function mint(User $user): string
    {
        $now = time();

        $payload = [
            'iss' => config('app.url'),
            'sub' => (string) $user->id,
            'iat' => $now,
            'exp' => $now + ($this->ttlMinutes() * 60),
            'purpose' => '2fa_challenge',
        ];

        return JWT::encode($payload, $this->secret(), self::ALGORITHM);
    }

    /**
     * @return array{user_id: int}|null
     */
    public function verify(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret(), self::ALGORITHM));

            $purpose = isset($decoded->purpose) ? (string) $decoded->purpose : '';

            if ($purpose !== '2fa_challenge') {
                return null;
            }

            $sub = isset($decoded->sub) ? (int) $decoded->sub : 0;

            if ($sub === 0) {
                return null;
            }

            return ['user_id' => $sub];
        } catch (Throwable) {
            return null;
        }
    }
}
