<?php

use App\Jobs\ProcessTelegramUpdate;
use App\Services\Telegram\MessageDispatcher;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.restaurants.provider' => 'fixture']);
    fakeTelegramApi();
});

it('sends sendVenue 5 times for 5 nearby restaurants', function () {
    $update = telegramFixture('update_location');

    (new ProcessTelegramUpdate($update))->handle(app(MessageDispatcher::class));

    $sendVenueCalls = collect(Http::recorded())->filter(
        fn ($pair) => str_contains((string) $pair[0]->url(), 'sendVenue')
    );

    expect($sendVenueCalls->count())->toBe(5);
});

it('sends inline keyboard with menu and reviews callback data for each restaurant', function () {
    $update = telegramFixture('update_location');

    (new ProcessTelegramUpdate($update))->handle(app(MessageDispatcher::class));

    $sendMessageWithMarkup = collect(Http::recorded())->filter(function ($pair) {
        $req = $pair[0];
        if (! str_contains((string) $req->url(), 'sendMessage')) {
            return false;
        }
        $body = $req->data();
        $markup = json_encode($body['reply_markup'] ?? []);

        return str_contains((string) $markup, 'menu:') && str_contains((string) $markup, 'rev:');
    });

    expect($sendMessageWithMarkup->count())->toBeGreaterThanOrEqual(1);
});
