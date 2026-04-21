<?php

use App\Jobs\ProcessTelegramUpdate;
use App\Models\UserSubmission;
use App\Services\Telegram\MessageDispatcher;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.restaurants.provider' => 'mock']);
    fakeTelegramApi();
});

it('creates a UserSubmission row with type=video and file_id, and sends "Got your video" message', function () {
    $update = telegramFixture('update_video');

    (new ProcessTelegramUpdate($update))->handle(app(MessageDispatcher::class));

    $submission = UserSubmission::where('type', 'video')->first();

    expect($submission)->not->toBeNull();
    expect($submission->file_id)->toBe('BAACAgIAAxkBAAIBBGZZaBCmMVgVZnNuABM5BAADB');

    Http::assertSent(function ($request) {
        return str_contains((string) $request->url(), 'sendMessage')
            && str_contains((string) $request->body(), 'Got your video');
    });
});

it('creates a UserSubmission for video even without a linked user', function () {
    // No TelegramUser created — unlinked chat
    $update = telegramFixture('update_video');

    (new ProcessTelegramUpdate($update))->handle(app(MessageDispatcher::class));

    expect(UserSubmission::where('type', 'video')->count())->toBe(1);
});
