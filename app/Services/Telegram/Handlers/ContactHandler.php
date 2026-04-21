<?php

namespace App\Services\Telegram\Handlers;

use App\Models\TelegramUser;
use App\Models\UserFavorite;
use App\Services\Telegram\TelegramBotService;

class ContactHandler implements MessageHandler
{
    public function __construct(private readonly TelegramBotService $telegram) {}

    /**
     * @param  array<string,mixed>  $update
     */
    public function handle(array $update): void
    {
        $message = $update['message'] ?? [];
        $chatId = $message['chat']['id'] ?? null;
        $contact = $message['contact'] ?? [];

        if ($chatId === null) {
            return;
        }

        $telegramUser = TelegramUser::where('chat_id', (string) $chatId)->first();

        if ($telegramUser === null) {
            $this->telegram->sendMessage($chatId, 'Link your account first via /link &lt;code&gt;.');

            return;
        }

        $phoneNumber = (string) ($contact['phone_number'] ?? '');
        $firstName = (string) ($contact['first_name'] ?? '');
        $lastName = (string) ($contact['last_name'] ?? '');
        $fullName = trim("{$firstName} {$lastName}");

        UserFavorite::create([
            'user_id' => $telegramUser->user_id,
            'name' => $fullName,
            'phone_number' => $phoneNumber,
        ]);

        $safeName = htmlspecialchars($fullName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $this->telegram->sendMessage($chatId, "Saved {$safeName} as a favorite.");
    }
}
