<?php

namespace App\Services\Telegram\Commands;

use App\Services\Telegram\TelegramBotService;

class CancelCommand implements Command
{
    public function __construct(private readonly TelegramBotService $telegram) {}

    /**
     * @param  array<string,mixed>  $message
     * @param  string[]  $args
     */
    public function handle(array $message, array $args): void
    {
        $chatId = $message['chat']['id'];

        $text = "🛑 <b>Cancelled.</b>\n"
            ."No problem — nothing was saved. Tap a button below or send <code>/start</code> for the main menu.";

        $inlineKeyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🏠 Main menu', 'callback_data' => 'nav:start'],
                    ['text' => '❓ Help', 'callback_data' => 'nav:help'],
                ],
            ],
        ];

        $this->telegram->sendMessage($chatId, $text, ['reply_markup' => $inlineKeyboard]);
    }
}
