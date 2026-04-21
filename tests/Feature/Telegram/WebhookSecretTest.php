<?php

use App\Jobs\ProcessTelegramUpdate;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    config(['services.restaurants.provider' => 'mock']);
});

it('accepts a valid webhook secret header and dispatches the job', function () {
    $payload = telegramFixture('update_text_search');

    $response = $this->postJson(
        '/api/telegram/webhook',
        $payload,
        telegramSecretHeader()
    );

    $response->assertStatus(200);
    Queue::assertPushed(ProcessTelegramUpdate::class);
});

it('rejects a request with missing webhook secret header with 403', function () {
    $payload = telegramFixture('update_text_search');

    $response = $this->postJson('/api/telegram/webhook', $payload);

    $response->assertStatus(403);
    Queue::assertNotPushed(ProcessTelegramUpdate::class);
});

it('rejects a request with an incorrect webhook secret header with 403', function () {
    $payload = telegramFixture('update_text_search');

    $response = $this->postJson(
        '/api/telegram/webhook',
        $payload,
        ['X-Telegram-Bot-Api-Secret-Token' => 'wrong-secret-value']
    );

    $response->assertStatus(403);
    Queue::assertNotPushed(ProcessTelegramUpdate::class);
});
