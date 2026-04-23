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
            $this->telegram->sendMessage(
                $chatId,
                "🔒 <b>Account not linked yet</b>\n"
                ."Saving contacts as favorites requires a linked account.\n\n"
                ."1. Generate a 6-digit code in the web app.\n"
                ."2. Send <code>/link 123456</code> here.\n"
                ."3. Share the contact again.",
                [
                    'reply_markup' => [
                        'inline_keyboard' => [[
                            ['text' => '❓ How to link', 'callback_data' => 'nav:help'],
                        ]],
                    ],
                ]
            );

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
        $safePhone = htmlspecialchars($phoneNumber, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $this->telegram->sendMessage(
            $chatId,
            "✅ <b>Saved as favorite</b>\n"
            ."👤 {$safeName}\n"
            ."📞 <code>{$safePhone}</code>\n\n"
            ."<i>You'll find this in your favorites in the web app.</i>"
        );
    }
}
