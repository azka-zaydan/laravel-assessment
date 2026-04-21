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
use App\Services\Auth\RecoveryVerified;
use App\Services\Auth\RefreshTokenService;
use App\Services\Auth\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
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

        Log::info('2fa.enable.attempt', ['user_id' => $user->id]);

        if ($user->two_factor_enabled) {
            Log::warning('2fa.enable.rejected', [
                'user_id' => $user->id,
                'reason' => 'already_enabled',
            ]);

            return response()->json(
                ['error' => '2FA is already enabled.'],
                Response::HTTP_FORBIDDEN,
            );
        }

        /** @var string $password */
        $password = $request->validated('password');

        if (! Hash::check($password, $user->password)) {
            Log::warning('2fa.enable.rejected', [
                'user_id' => $user->id,
                'reason' => 'wrong_password',
            ]);

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

        Log::info('2fa.enable.success', ['user_id' => $user->id]);

        return response()->json([
            'otpauth_url' => $otpauthUrl,
            'secret_masked' => $secretMasked,
        ]);
    }

    public function confirm(ConfirmRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        Log::info('2fa.confirm.attempt', ['user_id' => $user->id]);

        if ($user->two_factor_enabled) {
            Log::warning('2fa.confirm.rejected', [
                'user_id' => $user->id,
                'reason' => 'already_enabled',
            ]);

            return response()->json(
                ['error' => '2FA is already enabled. Use /2fa/recovery-codes/regenerate to rotate codes.'],
                Response::HTTP_FORBIDDEN,
            );
        }

        if ($user->two_factor_secret === null) {
            Log::warning('2fa.confirm.rejected', [
                'user_id' => $user->id,
                'reason' => 'setup_not_initiated',
            ]);

            return response()->json(
                ['message' => 'The given data was invalid.', 'errors' => ['code' => ['2FA setup not initiated.']]],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        /** @var string $code */
        $code = $request->validated('code');

        if (! $this->twoFactorService->verifyTotp((string) $user->two_factor_secret, $code)) {
            Log::warning('2fa.confirm.rejected', [
                'user_id' => $user->id,
                'reason' => 'invalid_totp',
            ]);

            return response()->json(
                ['message' => 'The given data was invalid.', 'errors' => ['code' => ['Invalid TOTP code.']]],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        /** @var array{plain: list<string>, hashed: list<string>}|null $codes */
        $codes = null;

        DB::transaction(function () use ($user, &$codes): void {
            $fresh = User::lockForUpdate()->findOrFail($user->id);
            if ($fresh->two_factor_enabled) {
                Log::warning('2fa.confirm.rejected', [
                    'user_id' => $fresh->id,
                    'reason' => 'already_enabled_race',
                ]);
                abort(Response::HTTP_FORBIDDEN, '2FA is already enabled.');
            }

            $codes = $this->twoFactorService->generateRecoveryCodes();
            $fresh->two_factor_enabled = true;
            $fresh->two_factor_recovery_codes = $codes['hashed'];
            $fresh->save();
        });

        /** @var array{plain: list<string>, hashed: list<string>} $codes */
        Log::info('2fa.confirm.success', [
            'user_id' => $user->id,
            'recovery_code_count' => count($codes['plain']),
        ]);

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

        Log::info('2fa.verify.attempt', ['ip' => $request->ip()]);

        $payload = $this->challengeTokenService->verify($challengeToken);

        if ($payload === null) {
            Log::warning('2fa.verify.rejected', [
                'reason' => 'invalid_challenge_token',
                'ip' => $request->ip(),
            ]);

            return response()->json(
                ['error' => 'Invalid or expired challenge token.'],
                Response::HTTP_UNAUTHORIZED,
            );
        }

        $user = User::find($payload['user_id']);

        if (! ($user instanceof User)) {
            Log::warning('2fa.verify.rejected', [
                'reason' => 'user_not_found',
                'user_id' => $payload['user_id'],
                'ip' => $request->ip(),
            ]);

            return response()->json(
                ['error' => 'Invalid or expired challenge token.'],
                Response::HTTP_UNAUTHORIZED,
            );
        }

        /** @var list<string> $recoveryCodes */
        $recoveryCodes = is_array($user->two_factor_recovery_codes) ? $user->two_factor_recovery_codes : [];

        $result = $this->twoFactorService->verify($user, $code, $recoveryCodes);

        if ($result === null) {
            Log::warning('2fa.verify.rejected', [
                'user_id' => $user->id,
                'reason' => 'invalid_code',
                'ip' => $request->ip(),
            ]);

            return response()->json(
                ['error' => 'Invalid code.'],
                Response::HTTP_UNAUTHORIZED,
            );
        }

        if ($result instanceof RecoveryVerified) {
            $user->two_factor_recovery_codes = $result->remainingCodes;
        }

        $user->two_factor_confirmed_at = now();
        $user->save();

        Log::info('2fa.verify.success', [
            'user_id' => $user->id,
            'method' => $result->method(),
            'ip' => $request->ip(),
        ]);

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

        Log::info('2fa.regenerate.attempt', ['user_id' => $user->id]);

        /** @var string $password */
        $password = $request->validated('password');

        if (! Hash::check($password, $user->password)) {
            Log::warning('2fa.regenerate.rejected', [
                'user_id' => $user->id,
                'reason' => 'wrong_password',
            ]);

            return response()->json(
                ['message' => 'The given data was invalid.', 'errors' => ['password' => ['Wrong password.']]],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $codes = $this->twoFactorService->generateRecoveryCodes();
        $user->two_factor_recovery_codes = $codes['hashed'];
        $user->save();

        Log::info('2fa.regenerate.success', [
            'user_id' => $user->id,
            'recovery_code_count' => count($codes['plain']),
        ]);

        return response()->json([
            'recovery_codes' => $codes['plain'],
        ]);
    }
}
