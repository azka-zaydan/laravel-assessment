<?php

namespace App\Services\Telegram\Commands;

use App\Services\Telegram\TelegramBotService;

class HelpCommand implements Command
{
    public function __construct(private readonly TelegramBotService $telegram) {}

    /**
     * @param  array<string,mixed>  $message
     * @param  string[]  $args
     */
    public function handle(array $message, array $args): void
    {
        $chatId = $message['chat']['id'];

        $text = "<b>Available Commands:</b>\n\n"
            ."<b>/start</b> — Show welcome message\n"
            ."<b>/help</b> — Show this help message\n"
            ."<b>/search &lt;query&gt;</b> — Search for restaurants by name or cuisine\n"
            ."<b>/link &lt;6-digit-code&gt;</b> — Link your app account to this chat\n\n"
            ."<b>Other features:</b>\n"
            ."• Share your location to find nearby restaurants\n"
            ."• Share a contact to save as a favorite (requires linked account)\n"
            ."• Send a photo to submit a menu photo for processing\n"
            .'• Send a video to submit a video clip';

        $this->telegram->sendMessage($chatId, $text);
    }
}
