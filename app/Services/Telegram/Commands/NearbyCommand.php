<?php

namespace App\Services\Telegram\Commands;

use App\Services\Telegram\TelegramBotService;

class NearbyCommand implements Command
{
    public function __construct(private readonly TelegramBotService $telegram) {}

    /**
     * @param  array<string,mixed>  $message
     * @param  string[]  $args
     */
    public function handle(array $message, array $args): void
    {
        $chatId = $message['chat']['id'];

        $text = "📍 <b>Find restaurants nearby</b>\n"
            ."Tap <b>Share location</b> below and I'll pull up 5 spots around you.\n\n"
            ."<i>Your location is used only for this search — we don't store it.</i>";

        // A one-shot reply keyboard with a request_location button. Telegram will
        // prompt the OS location dialog on tap.
        $replyKeyboard = [
            'keyboard' => [
                [['text' => '📍 Share location', 'request_location' => true]],
                [['text' => '🛑 Cancel']],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => true,
            'input_field_placeholder' => 'Tap "Share location" above',
        ];

        $this->telegram->sendMessage($chatId, $text, ['reply_markup' => $replyKeyboard]);
    }
}
