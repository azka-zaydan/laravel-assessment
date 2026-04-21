<?php

use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.telegram.bot_token' => 'TEST_TOKEN',
        'services.telegram.webhook_url' => 'https://example.com/api/telegram/webhook',
        'services.telegram.webhook_secret' => 'test-secret-token',
        'services.telegram.bot_api_base' => 'https://api.telegram.org',
    ]);
});

it('calls setWebhook Bot API endpoint with correct URL, secret and allowed_updates', function () {
    Http::fake([
        'https://api.telegram.org/bot*/setWebhook' => Http::response(
            ['ok' => true, 'result' => true, 'description' => 'Webhook was set'],
            200
        ),
    ]);

    $this->artisan('telegram:set-webhook')
        ->assertExitCode(0);

    Http::assertSent(function ($request) {
        if (! str_contains((string) $request->url(), 'setWebhook')) {
            return false;
        }

        $body = $request->data();

        return isset($body['url'])
            && str_contains((string) $body['url'], 'webhook')
            && isset($body['secret_token']);
    });
});

it('exits with non-zero code when Bot API returns an error', function () {
    Http::fake([
        'https://api.telegram.org/bot*/setWebhook' => Http::response(
            ['ok' => false, 'error_code' => 400, 'description' => 'Bad webhook: Failed to resolve host'],
            400
        ),
    ]);

    $this->artisan('telegram:set-webhook')
        ->assertExitCode(1);
});
