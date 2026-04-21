<?php

use App\Services\Telegram\TelegramBotService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    config([
        'services.telegram.bot_token' => 'TEST_TOKEN_123',
        'services.telegram.bot_api_base' => 'https://api.telegram.org',
    ]);
});

it('POSTs to the correct sendMessage URL with the correct body', function () {
    Http::fake([
        'https://api.telegram.org/botTEST_TOKEN_123/sendMessage' => Http::response(
            ['ok' => true, 'result' => ['message_id' => 42]],
            200
        ),
    ]);

    $service = app(TelegramBotService::class);
    $service->sendMessage(999888777, 'Hello World');

    Http::assertSent(function ($request) {
        $url = (string) $request->url();
        $body = $request->data();

        return str_contains($url, 'https://api.telegram.org/botTEST_TOKEN_123/sendMessage')
            && $body['chat_id'] === 999888777
            && $body['text'] === 'Hello World';
    });
});

it('retries on transient 5xx errors before succeeding', function () {
    $callCount = 0;

    Http::fake(function ($request) use (&$callCount) {
        $callCount++;
        if ($callCount < 3) {
            return Http::response(['ok' => false, 'description' => 'Server Error'], 500);
        }

        return Http::response(['ok' => true, 'result' => ['message_id' => 1]], 200);
    });

    $service = app(TelegramBotService::class);
    $service->sendMessage(111222333, 'Retry test');

    // retry(2, ...) means up to 3 total attempts (1 initial + 2 retries)
    expect($callCount)->toBeGreaterThanOrEqual(2);
});

it('logs an error but does not throw when the Bot API returns a terminal 4xx failure', function () {
    Http::fake([
        'https://api.telegram.org/bot*/*' => Http::response(
            ['ok' => false, 'error_code' => 403, 'description' => 'Forbidden: bot was blocked by the user'],
            403
        ),
    ]);

    Log::spy();

    $service = app(TelegramBotService::class);

    // safeSend (used by sendMessage) must not throw — it should catch and log
    expect(fn () => $service->sendMessage(111222333, 'This should not throw'))->not->toThrow(Exception::class);

    Log::shouldHaveReceived('error')->once()->withArgs(function ($message, $context) {
        return str_contains((string) $message, 'TelegramBotService') || isset($context['error']);
    });
});
