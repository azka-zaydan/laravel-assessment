<?php

namespace App\Services\Telegram\Commands;

use App\Services\Telegram\TelegramBotService;

class StartCommand implements Command
{
    public function __construct(private readonly TelegramBotService $telegram) {}

    /**
     * @param  array<string,mixed>  $message
     * @param  string[]  $args
     */
    public function handle(array $message, array $args): void
    {
        $chatId = $message['chat']['id'];

        $text = "<b>Welcome to the Restaurant Bot!</b>\n\n"
            ."Here's what I can do:\n"
            ."<b>/start</b> — Show this welcome message\n"
            ."<b>/help</b> — Show available commands\n"
            ."<b>/search &lt;query&gt;</b> — Search for restaurants\n"
            ."<b>/link &lt;6-digit-code&gt;</b> — Link your account\n\n"
            .'You can also share your <b>location</b> to find nearby restaurants, '
            .'share a <b>contact</b> to save as a favorite, or send a <b>photo/video</b>.';

        $replyKeyboard = [
            'keyboard' => [
                [['text' => 'Search'], ['text' => 'Nearby']],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];

        $this->telegram->sendMessage($chatId, $text, ['reply_markup' => $replyKeyboard]);
    }
}
