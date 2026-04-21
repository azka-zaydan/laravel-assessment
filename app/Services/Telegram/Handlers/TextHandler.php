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

        $resolved = $this->commandRegistry->resolve($text);

        if ($resolved !== null) {
            [$command, $args] = $resolved;
            $command->handle($message, $args);

            return;
        }

        $this->telegram->sendMessage($chatId, 'Unknown command. Type /help for options.');
    }
}
