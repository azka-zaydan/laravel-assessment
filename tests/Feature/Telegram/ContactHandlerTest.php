<?php

use App\Jobs\ProcessTelegramUpdate;
use App\Models\TelegramUser;
use App\Models\User;
use App\Models\UserFavorite;
use App\Services\Telegram\MessageDispatcher;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.restaurants.provider' => 'mock']);
    fakeTelegramApi();
});

it('creates a UserFavorite row and sends confirmation when linked user shares a contact', function () {
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

    expect(UserFavorite::where('user_id', $user->id)->count())->toBe(1);

    Http::assertSent(function ($request) {
        return str_contains((string) $request->url(), 'sendMessage')
            && str_contains((string) $request->body(), 'favorite');
    });
});

it('sends link account prompt when unlinked chat shares a contact', function () {
    $update = telegramFixture('update_contact');

    (new ProcessTelegramUpdate($update))->handle(app(MessageDispatcher::class));

    Http::assertSent(function ($request) {
        if (! str_contains((string) $request->url(), 'sendMessage')) {
            return false;
        }

        return str_contains(strtolower((string) $request->body()), 'link');
    });

    expect(UserFavorite::count())->toBe(0);
});

it('persists the correct phone number and full name in UserFavorite', function () {
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

    $favorite = UserFavorite::where('user_id', $user->id)->first();

    expect($favorite)->not->toBeNull();
    expect($favorite->phone_number)->toBe('+628123456789');
    expect($favorite->name)->toContain('John');
});
