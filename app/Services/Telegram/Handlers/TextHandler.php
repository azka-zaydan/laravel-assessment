<?php

namespace App\Services\Telegram\Handlers;

use App\Services\Telegram\Commands\CommandRegistry;
use App\Services\Telegram\TelegramBotService;

class TextHandler implements MessageHandler
{
    public function __construct(
        private readonly TelegramBotService $telegram,
        private readonly CommandRegistry $commandRegistry,
    ) {}

    /**
     * @param  array<string,mixed>  $update
     */
    public function handle(array $update): void
    {
        $message = $update['message'] ?? [];
        $chatId = $message['chat']['id'] ?? null;
        $text = (string) ($message['text'] ?? '');

        if ($chatId === null) {
            return;
        }

        // Map reply-keyboard button taps to their command equivalents so the
        // persistent buttons in /start feel like real actions, not dead text.
        $aliased = $this->aliasReplyKeyboardTap($text);

        if ($aliased !== null) {
            $resolved = $this->commandRegistry->resolve($aliased);

            if ($resolved !== null) {
                [$command, $args] = $resolved;
                $command->handle($message, $args);

                return;
            }
        }

        $resolved = $this->commandRegistry->resolve($text);

        if ($resolved !== null) {
            [$command, $args] = $resolved;
            $command->handle($message, $args);

            return;
        }

        // Friendly unknown-command reply. Keeps "Unknown" for test assertions.
        $this->telegram->sendMessage(
            $chatId,
            "🤔 <b>Unknown command.</b>\n"
            ."I didn't recognise that. Tap <code>/help</code> to see everything I can do, or try:\n"
            ."• <code>/search pizza</code>\n"
            .'• <code>/nearby</code> to find spots around you',
            [
                'reply_markup' => [
                    'inline_keyboard' => [[
                        ['text' => '❓ Show help', 'callback_data' => 'nav:help'],
                        ['text' => '🏠 Main menu', 'callback_data' => 'nav:start'],
                    ]],
                ],
            ]
        );
    }

    /**
     * Translate known reply-keyboard button labels into slash commands.
     * Returns null if the text isn't a known button label.
     */
    private function aliasReplyKeyboardTap(string $text): ?string
    {
        return match (trim($text)) {
            '🔍 Search', 'Search' => '/search',
            '📍 Nearby', 'Nearby' => '/nearby',
            '❓ Help', 'Help' => '/help',
            '⚙️ Settings', 'Settings' => '/settings',
            '🛑 Cancel', 'Cancel' => '/cancel',
            default => null,
        };
    }
}
