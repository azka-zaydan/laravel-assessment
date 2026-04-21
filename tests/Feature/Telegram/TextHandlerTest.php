<?php

use App\Jobs\ProcessTelegramUpdate;
use App\Services\Telegram\MessageDispatcher;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.restaurants.provider' => 'mock']);
    fakeTelegramApi();
});

it('sends welcome message with reply_markup keyboard on /start command', function () {
    $update = [
        'update_id' => 200000001,
        'message' => [
            'message_id' => 1,
            'from' => ['id' => 123456789, 'is_bot' => false, 'first_name' => 'John'],
            'chat' => ['id' => 123456789, 'type' => 'private'],
            'date' => 1745200000,
            'text' => '/start',
        ],
    ];

    (new ProcessTelegramUpdate($update))->handle(app(MessageDispatcher::class));

    Http::assertSent(function ($request) {
        if (! str_contains((string) $request->url(), 'sendMessage')) {
            return false;
        }
        $body = $request->data();

        return isset($body['reply_markup'])
            && str_contains((string) $body['text'], 'Welcome');
    });
});

it('sends command list on /help command', function () {
    $update = [
        'update_id' => 200000002,
        'message' => [
            'message_id' => 2,
            'from' => ['id' => 123456789, 'is_bot' => false, 'first_name' => 'John'],
            'chat' => ['id' => 123456789, 'type' => 'private'],
            'date' => 1745200001,
            'text' => '/help',
        ],
    ];

    (new ProcessTelegramUpdate($update))->handle(app(MessageDispatcher::class));

    Http::assertSent(function ($request) {
        if (! str_contains((string) $request->url(), 'sendMessage')) {
            return false;
        }
        $body = $request->data();
        $text = (string) ($body['text'] ?? '');

        return str_contains($text, '/search') && str_contains($text, '/link');
    });
});

it('sends sendVenue calls and inline keyboard with menu/rev callback data on /search pizza', function () {
    $update = telegramFixture('update_text_search');

    (new ProcessTelegramUpdate($update))->handle(app(MessageDispatcher::class));

    $sendVenueCalls = collect(Http::recorded())->filter(
        fn ($pair) => str_contains((string) $pair[0]->url(), 'sendVenue')
    );

    // MockProvider search.json with query "pizza" returns restaurants whose name contains "pizza"
    expect($sendVenueCalls->count())->toBeGreaterThanOrEqual(1);

    Http::assertSent(function ($request) {
        if (! str_contains((string) $request->url(), 'sendMessage')) {
            return false;
        }
        $body = $request->data();
        $markup = json_encode($body['reply_markup'] ?? []);

        return str_contains((string) $markup, 'menu:') && str_contains((string) $markup, 'rev:');
    });
});

it('sends hint message when /search is called with empty query', function () {
    $update = [
        'update_id' => 200000004,
        'message' => [
            'message_id' => 4,
            'from' => ['id' => 123456789, 'is_bot' => false, 'first_name' => 'John'],
            'chat' => ['id' => 123456789, 'type' => 'private'],
            'date' => 1745200003,
            'text' => '/search',
        ],
    ];

    (new ProcessTelegramUpdate($update))->handle(app(MessageDispatcher::class));

    Http::assertSent(function ($request) {
        if (! str_contains((string) $request->url(), 'sendMessage')) {
            return false;
        }
        $body = $request->data();

        return str_contains(strtolower((string) ($body['text'] ?? '')), 'usage');
    });
});

it('sends Unknown command message on unknown command /foo', function () {
    $update = [
        'update_id' => 200000005,
        'message' => [
            'message_id' => 5,
            'from' => ['id' => 123456789, 'is_bot' => false, 'first_name' => 'John'],
            'chat' => ['id' => 123456789, 'type' => 'private'],
            'date' => 1745200004,
            'text' => '/foo',
        ],
    ];

    (new ProcessTelegramUpdate($update))->handle(app(MessageDispatcher::class));

    Http::assertSent(function ($request) {
        if (! str_contains((string) $request->url(), 'sendMessage')) {
            return false;
        }
        $body = $request->data();

        return str_contains(strtolower((string) ($body['text'] ?? '')), 'unknown');
    });
});
