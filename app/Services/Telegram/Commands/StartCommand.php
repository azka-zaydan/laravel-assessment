<?php

namespace App\Services\Telegram\Commands;

use App\Models\TelegramUser;
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
        $from = $message['from'] ?? [];
        $firstName = (string) ($from['first_name'] ?? '');
        $greeting = $firstName !== ''
            ? 'Welcome, '.htmlspecialchars($firstName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'!'
            : 'Welcome!';

        $isLinked = TelegramUser::where('chat_id', (string) $chatId)->exists();
        $accountLine = $isLinked
            ? '✅ <b>Account linked</b> — your favorites are saved to your profile.'
            : '🔒 <b>Account not linked</b> — use <code>/link &lt;code&gt;</code> to enable favorites.';

        $text = "🍽️ <b>{$greeting}</b>\n"
            ."I'm your pocket restaurant concierge — discover spots nearby, peek at menus, and read reviews without leaving chat.\n\n"
            ."<b>Try these right now:</b>\n"
            ."🔍  <code>/search pizza</code> — find restaurants by name or cuisine\n"
            ."📍  Share your <b>location</b> — discover what's nearby\n"
            ."❓  <code>/help</code> — full command list &amp; tips\n\n"
            .$accountLine;

        // Inline keyboard: hierarchical quick-actions that edit-in-place when tapped
        $inlineKeyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🔍 Search', 'callback_data' => 'nav:search'],
                    ['text' => '📍 Nearby', 'callback_data' => 'nav:nearby'],
                ],
                [
                    ['text' => '❓ Help', 'callback_data' => 'nav:help'],
                    ['text' => '⚙️ Settings', 'callback_data' => 'nav:settings'],
                ],
            ],
        ];

        // Reply keyboard: persistent shortcuts at the bottom of the input.
        // "Nearby" uses request_location so the OS prompts for location on tap.
        $replyKeyboard = [
            'keyboard' => [
                [
                    ['text' => '🔍 Search'],
                    ['text' => '📍 Nearby', 'request_location' => true],
                ],
                [
                    ['text' => '❓ Help'],
                    ['text' => '⚙️ Settings'],
                ],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
            'input_field_placeholder' => 'Type /search or tap a button',
        ];

        $this->telegram->sendMessage($chatId, $text, ['reply_markup' => $inlineKeyboard]);

        $this->telegram->sendMessage(
            $chatId,
            '💡 <i>Tip: use the keyboard below for quick access anytime.</i>',
            ['reply_markup' => $replyKeyboard]
        );
    }
}
