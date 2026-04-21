<?php

use App\Jobs\ProcessTelegramUpdate;
use App\Models\TelegramUser;
use App\Models\User;
use App\Services\Telegram\LinkCodeService;
use App\Services\Telegram\MessageDispatcher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    config(['services.restaurants.provider' => 'mock']);
    fakeTelegramApi();
});

it('returns a 6-digit code and expires_in for an authenticated 2FA-confirmed user', function () {
    $user = actAsConfirmedUser();

    $response = $this->postJson('/api/telegram/link-code');

    $response->assertStatus(200)
        ->assertJsonStructure(['code', 'expires_in']);

    expect($response->json('expires_in'))->toBe(600);
    expect($response->json('code'))->toMatch('/^\d{6}$/');
});

it('returns 401 for unauthenticated requests to link-code endpoint', function () {
    $response = $this->postJson('/api/telegram/link-code');

    $response->assertStatus(401);
});

it('returns 403 for an authenticated user with 2FA enabled but not confirmed', function () {
    // User has 2FA enabled but two_factor_confirmed_at is null (never confirmed)
    $user = User::factory()->withTwoFactor()->create();
    $user->forceFill(['two_factor_confirmed_at' => null])->save();

    $token = $user->createToken('test')->accessToken;
    $this->withToken($token);

    $response = $this->postJson('/api/telegram/link-code');

    $response->assertStatus(403);
});

it('creates a TelegramUser row and sends Linked! on valid /link code', function () {
    $user = User::factory()->create();
    $linkCodeService = app(LinkCodeService::class);
    $code = $linkCodeService->generate($user);

    $update = [
        'update_id' => 400000004,
        'message' => [
            'message_id' => 40,
            'from' => [
                'id' => 123456789,
                'is_bot' => false,
                'first_name' => 'John',
                'last_name' => 'Doe',
                'username' => 'johndoe',
                'language_code' => 'en',
            ],
            'chat' => ['id' => 123456789, 'type' => 'private'],
            'date' => 1745200040,
            'text' => "/link {$code}",
        ],
    ];

    (new ProcessTelegramUpdate($update))->handle(app(MessageDispatcher::class));

    expect(TelegramUser::where('chat_id', '123456789')->exists())->toBeTrue();

    Http::assertSent(function ($request) {
        if (! str_contains((string) $request->url(), 'sendMessage')) {
            return false;
        }
        $body = $request->data();

        return str_contains(strtolower((string) ($body['text'] ?? '')), 'linked');
    });
});

it('sends error message on /link with invalid code 000000', function () {
    // Ensure code 000000 does not exist in Redis
    Redis::del('telegram:link:000000');

    $update = [
        'update_id' => 400000005,
        'message' => [
            'message_id' => 41,
            'from' => ['id' => 123456789, 'is_bot' => false, 'first_name' => 'John'],
            'chat' => ['id' => 123456789, 'type' => 'private'],
            'date' => 1745200041,
            'text' => '/link 000000',
        ],
    ];

    (new ProcessTelegramUpdate($update))->handle(app(MessageDispatcher::class));

    expect(TelegramUser::count())->toBe(0);

    Http::assertSent(function ($request) {
        if (! str_contains((string) $request->url(), 'sendMessage')) {
            return false;
        }
        $body = $request->data();
        $text = strtolower((string) ($body['text'] ?? ''));

        return str_contains($text, 'invalid') || str_contains($text, 'expired');
    });
});
