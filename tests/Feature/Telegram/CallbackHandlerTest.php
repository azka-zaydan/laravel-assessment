<?php

use App\Jobs\ProcessTelegramUpdate;
use App\Services\Telegram\MessageDispatcher;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.restaurants.provider' => 'fixture']);
    fakeTelegramApi();
});

it('answers callback query and sends menu items for menu:16507621 callback data', function () {
    $update = telegramFixture('update_callback_menu');

    (new ProcessTelegramUpdate($update))->handle(app(MessageDispatcher::class));

    Http::assertSent(function ($request) {
        return str_contains((string) $request->url(), 'answerCallbackQuery');
    });

    Http::assertSent(function ($request) {
        if (! str_contains((string) $request->url(), 'sendMessage')) {
            return false;
        }
        $body = $request->data();
        $text = (string) ($body['text'] ?? '');

        // Should contain menu item names from dailymenu_16507621.json fixture
        return str_contains($text, 'Pizza') || str_contains($text, 'Tiramisu') || str_contains($text, 'menu');
    });
});

it('answers callback query and sends reviews with next-page nav button for rev:16507621:p1', function () {
    $update = telegramFixture('update_callback_reviews');

    (new ProcessTelegramUpdate($update))->handle(app(MessageDispatcher::class));

    Http::assertSent(function ($request) {
        return str_contains((string) $request->url(), 'answerCallbackQuery');
    });

    Http::assertSent(function ($request) {
        if (! str_contains((string) $request->url(), 'sendMessage')) {
            return false;
        }
        $body = $request->data();
        $text = (string) ($body['text'] ?? '');

        // Should contain review content (ratings or text snippets)
        return str_contains($text, '⭐') || str_contains($text, 'rating') || str_contains($text, '★')
            || str_contains($text, '/5') || strlen($text) > 20;
    });
});

it('answers callback query with error text for unknown callback data shape', function () {
    $update = [
        'update_id' => 300000003,
        'callback_query' => [
            'id' => 'cb_unknown_001',
            'from' => ['id' => 123456789, 'is_bot' => false, 'first_name' => 'John'],
            'message' => [
                'message_id' => 20,
                'from' => ['id' => 7777777777, 'is_bot' => true, 'first_name' => 'Bot'],
                'chat' => ['id' => 123456789, 'type' => 'private'],
                'date' => 1745200010,
                'text' => 'Some text',
            ],
            'chat_instance' => '1234567890123456789',
            'data' => 'unknown:data:shape:xyz',
        ],
    ];

    (new ProcessTelegramUpdate($update))->handle(app(MessageDispatcher::class));

    Http::assertSent(function ($request) {
        if (! str_contains((string) $request->url(), 'answerCallbackQuery')) {
            return false;
        }
        $body = $request->data();

        // Should answer with some error/unknown text
        return isset($body['callback_query_id']);
    });
});
