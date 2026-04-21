<?php

namespace App\Services\Telegram\Commands;

use App\Models\TelegramUser;
use App\Models\User;
use App\Services\Telegram\LinkCodeService;
use App\Services\Telegram\TelegramBotService;

class LinkCommand implements Command
{
    public function __construct(
        private readonly TelegramBotService $telegram,
        private readonly LinkCodeService $linkCodeService,
    ) {}

    /**
     * @param  array<string,mixed>  $message
     * @param  string[]  $args
     */
    public function handle(array $message, array $args): void
    {
        $chatId = $message['chat']['id'];
        $from = $message['from'] ?? [];

        if ($args === [] || ! preg_match('/^\d{6}$/', trim($args[0]))) {
            $this->telegram->sendMessage($chatId, 'Usage: /link &lt;6-digit-code&gt;');

            return;
        }

        $code = trim($args[0]);
        $userId = $this->linkCodeService->consume($code);

        if ($userId === null) {
            $this->telegram->sendMessage($chatId, 'Invalid or expired code. Please generate a new code from the app.');

            return;
        }

        $user = User::find($userId);

        if ($user === null) {
            $this->telegram->sendMessage($chatId, 'Account not found. Please try again.');

            return;
        }

        TelegramUser::updateOrCreate(
            ['chat_id' => (string) $chatId],
            [
                'user_id' => $userId,
                'username' => isset($from['username']) ? (string) $from['username'] : null,
                'first_name' => isset($from['first_name']) ? (string) $from['first_name'] : null,
                'last_name' => isset($from['last_name']) ? (string) $from['last_name'] : null,
                'language_code' => isset($from['language_code']) ? (string) $from['language_code'] : null,
                'last_message_at' => now(),
            ]
        );

        $safeEmail = htmlspecialchars((string) $user->email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $this->telegram->sendMessage($chatId, "Linked! You are now connected to account {$safeEmail}.");
    }
}
