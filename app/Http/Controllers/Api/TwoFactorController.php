<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TwoFactor\ConfirmRequest;
use App\Http\Requests\TwoFactor\EnableRequest;
use App\Http\Requests\TwoFactor\RegenerateRequest;
use App\Http\Requests\TwoFactor\VerifyRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\ChallengeTokenService;
use App\Services\Auth\RefreshTokenService;
use App\Services\Auth\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorController extends Controller
{
    public function __construct(
        private readonly TwoFactorService $twoFactorService,
        private readonly ChallengeTokenService $challengeTokenService,
        private readonly RefreshTokenService $refreshTokenService,
    ) {}

    public function enable(EnableRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->two_factor_enabled) {
            return response()->json(
                ['error' => '2FA is already enabled.'],
                Response::HTTP_FORBIDDEN,
            );
        }

        /** @var string $password */
        $password = $request->validated('password');

        if (! Hash::check($password, $user->password)) {
            return response()->json(
                ['message' => 'The given data was invalid.', 'errors' => ['password' => ['Wrong password.']]],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $secret = $this->twoFactorService->generateSecret();
        $user->two_factor_secret = $secret;
        $user->save();

        $otpauthUrl = $this->twoFactorService->buildOtpauthUrl($user, $secret);
        $secretMasked = $this->twoFactorService->maskSecret($secret);

        return response()->json([
            'otpauth_url' => $otpauthUrl,
            'secret_masked' => $secretMasked,
        ]);
    }

    public function confirm(ConfirmRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->two_factor_secret === null) {
            return response()->json(
                ['message' => 'The given data was invalid.', 'errors' => ['code' => ['2FA setup not initiated.']]],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        /** @var string $code */
        $code = $request->validated('code');

        if (! $this->twoFactorService->verifyTotp((string) $user->two_factor_secret, $code)) {
            return response()->json(
                ['message' => 'The given data was invalid.', 'errors' => ['code' => ['Invalid TOTP code.']]],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $codes = $this->twoFactorService->generateRecoveryCodes();

        $user->two_factor_enabled = true;
        $user->two_factor_recovery_codes = $codes['hashed'];
        $user->save();

        return response()->json([
            'recovery_codes' => $codes['plain'],
        ]);
    }

    public function verify(VerifyRequest $request): JsonResponse
    {
        /** @var string $challengeToken */
        $challengeToken = $request->validated('challenge_token');

        /** @var string $code */
        $code = $request->validated('code');

        $payload = $this->challengeTokenService->verify($challengeToken);

        if ($payload === null) {
            return response()->json(
                ['error' => 'Invalid or expired challenge token.'],
                Response::HTTP_UNAUTHORIZED,
            );
        }

        $user = User::find($payload['user_id']);

        if (! ($user instanceof User)) {
            return response()->json(
                ['error' => 'Invalid or expired challenge token.'],
                Response::HTTP_UNAUTHORIZED,
            );
        }

        /** @var list<string> $recoveryCodes */
        $recoveryCodes = is_array($user->two_factor_recovery_codes) ? $user->two_factor_recovery_codes : [];

        $result = $this->twoFactorService->verify($user, $code, $recoveryCodes);

        if ($result === null) {
            return response()->json(
                ['error' => 'Invalid code.'],
                Response::HTTP_UNAUTHORIZED,
            );
        }

        if ($result['method'] === 'recovery' && isset($result['remaining_codes'])) {
            $user->two_factor_recovery_codes = $result['remaining_codes'];
        }

        $user->two_factor_confirmed_at = now();
        $user->save();

        $tokenResult = $user->createToken('access_token');
        $accessToken = $tokenResult->accessToken;
        $expiresIn = (int) $tokenResult->expiresIn;

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

    public function regenerate(RegenerateRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var string $password */
        $password = $request->validated('password');

        if (! Hash::check($password, $user->password)) {
            return response()->json(
                ['message' => 'The given data was invalid.', 'errors' => ['password' => ['Wrong password.']]],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $codes = $this->twoFactorService->generateRecoveryCodes();

        $user->two_factor_recovery_codes = $codes['hashed'];
        $user->save();

        return response()->json([
            'recovery_codes' => $codes['plain'],
        ]);
    }
}
