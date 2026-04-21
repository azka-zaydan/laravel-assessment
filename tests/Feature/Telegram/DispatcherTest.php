<?php

use App\Jobs\ProcessPhotoSubmission;
use App\Jobs\ProcessTelegramUpdate;
use App\Models\TelegramUser;
use App\Models\User;
use App\Services\Telegram\MessageDispatcher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config(['services.restaurants.provider' => 'mock']);
    fakeTelegramApi();
});

it('routes callback_query to CallbackHandler which sends answerCallbackQuery', function () {
    $update = telegramFixture('update_callback_menu');

    (new ProcessTelegramUpdate($update))->handle(app(MessageDispatcher::class));

    Http::assertSent(function ($request) {
        return str_contains((string) $request->url(), 'answerCallbackQuery');
    });
});

it('routes message with location to LocationHandler which sends sendVenue 5 times', function () {
    $update = telegramFixture('update_location');

    (new ProcessTelegramUpdate($update))->handle(app(MessageDispatcher::class));

    $sendVenueCalls = collect(Http::recorded())->filter(
        fn ($pair) => str_contains((string) $pair[0]->url(), 'sendVenue')
    );

    expect($sendVenueCalls->count())->toBe(5);
});

it('routes message with contact to ContactHandler which sends sendMessage with "favorite" text', function () {
    Queue::fake();

    // Create a linked user so the contact handler can proceed
    $user = User::factory()->create();
    TelegramUser::create([
        'chat_id' => '123456789',
        'user_id' => $user->id,
        'first_name' => 'John',
        'last_name' => 'Doe',
        'username' => 'johndoe',
        'language_code' => 'en',
    ]);

    $update = telegramFixture('update_contact');

    (new ProcessTelegramUpdate($update))->handle(app(MessageDispatcher::class));

    Http::assertSent(function ($request) {
        return str_contains((string) $request->url(), 'sendMessage')
            && str_contains((string) $request->body(), 'favorite');
    });
});

it('routes message with video to VideoHandler which sends sendMessage with "Got your video"', function () {
    $update = telegramFixture('update_video');

    (new ProcessTelegramUpdate($update))->handle(app(MessageDispatcher::class));

    Http::assertSent(function ($request) {
        return str_contains((string) $request->url(), 'sendMessage')
            && str_contains((string) $request->body(), 'Got your video');
    });
});

it('routes message with photo to PhotoHandler which sends sendMessage and dispatches ProcessPhotoSubmission', function () {
    Queue::fake();

    $update = telegramFixture('update_photo');

    (new ProcessTelegramUpdate($update))->handle(app(MessageDispatcher::class));

    Http::assertSent(function ($request) {
        return str_contains((string) $request->url(), 'sendMessage');
    });

    Queue::assertPushed(ProcessPhotoSubmission::class);
});

it('routes message with text to TextHandler which processes the /search command', function () {
    $update = telegramFixture('update_text_search');

    (new ProcessTelegramUpdate($update))->handle(app(MessageDispatcher::class));

    Http::assertSent(function ($request) {
        return str_contains((string) $request->url(), 'sendVenue')
            || str_contains((string) $request->url(), 'sendMessage');
    });
});
