<?php

use App\Jobs\ProcessPhotoSubmission;
use App\Jobs\ProcessTelegramUpdate;
use App\Models\UserSubmission;
use App\Services\Telegram\MessageDispatcher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config(['services.restaurants.provider' => 'fixture']);
    fakeTelegramApi();
    Queue::fake();
});

it('creates a UserSubmission row using the largest PhotoSize file_id', function () {
    $update = telegramFixture('update_photo');

    (new ProcessTelegramUpdate($update))->handle(app(MessageDispatcher::class));

    $submission = UserSubmission::where('type', 'photo')->first();

    expect($submission)->not->toBeNull();
    // The largest photo in the fixture is the last element (highest file_size)
    expect($submission->file_id)->toBe('AgACAgIAAxkBAAIBBWZZaBCLarge123456');
});

it('dispatches ProcessPhotoSubmission job after receiving a photo message', function () {
    $update = telegramFixture('update_photo');

    (new ProcessTelegramUpdate($update))->handle(app(MessageDispatcher::class));

    Queue::assertPushed(ProcessPhotoSubmission::class);
});

it('sends a "Got your photo. Processing menu..." message after photo submission', function () {
    $update = telegramFixture('update_photo');

    (new ProcessTelegramUpdate($update))->handle(app(MessageDispatcher::class));

    Http::assertSent(function ($request) {
        if (! str_contains((string) $request->url(), 'sendMessage')) {
            return false;
        }
        $body = $request->data();

        return str_contains((string) ($body['text'] ?? ''), 'Got your photo');
    });
});
