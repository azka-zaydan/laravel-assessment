<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\ChallengeTokenService;
use App\Services\Auth\RefreshTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\AccessToken;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function __construct(
        private readonly RefreshTokenService $refreshTokenService,
        private readonly ChallengeTokenService $challengeTokenService,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
        ]);

        $result = $user->createToken('access_token');
        $accessToken = $result->accessToken;
        $expiresIn = (int) $result->expiresIn;

        $plain = $this->refreshTokenService->mint($user, $request);
        $opts = $this->refreshTokenService->cookieOptions();

        return response()
            ->json([
                'access_token' => $accessToken,
                'token_type' => 'Bearer',
                'expires_in' => $expiresIn,
                'user' => new UserResource($user),
            ], Response::HTTP_CREATED)
            ->cookie(
                'refresh_token',
                $plain,
                (int) ($opts['max_age'] / 60),
                $opts['path'],
                null,
                (bool) $opts['secure'],
                (bool) $opts['http_only'],
                false,
                $opts['same_site'],
            );
    }

    public function login(LoginRequest $request): JsonResponse
    {
        /** @var string $email */
        $email = $request->validated('email');

        /** @var string $password */
        $password = $request->validated('password');

        $user = User::where('email', $email)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            return response()->json(
                ['error' => 'Invalid credentials.'],
                Response::HTTP_UNAUTHORIZED,
            );
        }

        // 2FA is enabled → return challenge token
        if ($user->two_factor_enabled) {
            $challengeToken = $this->challengeTokenService->mint($user);

            return response()->json([
                'challenge_token' => $challengeToken,
                'two_factor_required' => true,
                'expires_in' => 300,
            ]);
        }

        $result = $user->createToken('access_token');
        $accessToken = $result->accessToken;
        $expiresIn = (int) $result->expiresIn;

        $plain = $this->refreshTokenService->mint($user, $request);
        $opts = $this->refreshTokenService->cookieOptions();

        return response()
            ->json([
                'access_token' => $accessToken,
                'token_type' => 'Bearer',
                'expires_in' => $expiresIn,
                'user' => new UserResource($user),
            ])
            ->cookie(
                'refresh_token',
                $plain,
                (int) ($opts['max_age'] / 60),
                $opts['path'],
                null,
                (bool) $opts['secure'],
                (bool) $opts['http_only'],
                false,
                $opts['same_site'],
            );
    }

    public function refresh(Request $request): JsonResponse
    {
        $plain = $request->cookie('refresh_token');

        if (! is_string($plain) || $plain === '') {
            return response()->json(
                ['error' => 'Refresh token missing.'],
                Response::HTTP_UNAUTHORIZED,
            );
        }

        $refreshToken = $this->refreshTokenService->verify($plain);

        if ($refreshToken === null) {
            return response()->json(
                ['error' => 'Invalid or expired refresh token.'],
                Response::HTTP_UNAUTHORIZED,
            );
        }

        /** @var User $user */
        $user = $refreshToken->user;

        $newPlain = $this->refreshTokenService->rotate($refreshToken, $request);
        $opts = $this->refreshTokenService->cookieOptions();

        $result = $user->createToken('access_token');
        $accessToken = $result->accessToken;
        $expiresIn = (int) $result->expiresIn;

        return response()
            ->json([
                'access_token' => $accessToken,
                'token_type' => 'Bearer',
                'expires_in' => $expiresIn,
            ])
            ->cookie(
                'refresh_token',
                $newPlain,
                (int) ($opts['max_age'] / 60),
                $opts['path'],
                null,
                (bool) $opts['secure'],
                (bool) $opts['http_only'],
                false,
                $opts['same_site'],
            );
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var AccessToken<mixed> $accessToken */
        $accessToken = $user->token();
        $accessToken->revoke();

        $opts = $this->refreshTokenService->cookieOptions();

        return response()
            ->json(null, Response::HTTP_NO_CONTENT)
            ->cookie(
                'refresh_token',
                '',
                -1,
                $opts['path'],
                null,
                (bool) $opts['secure'],
                (bool) $opts['http_only'],
                false,
                $opts['same_site'],
            );
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($request->user()),
        ]);
    }
}
