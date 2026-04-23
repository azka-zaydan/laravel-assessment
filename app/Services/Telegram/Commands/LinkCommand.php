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
            $this->telegram->sendMessage(
                $chatId,
                "🔗 <b>Link your account</b>\n"
                ."Usage: <code>/link &lt;6-digit-code&gt;</code>\n\n"
                ."<b>How to get a code:</b>\n"
                ."1. Open the web app &amp; sign in.\n"
                ."2. Go to <b>Settings → Connect Telegram</b>.\n"
                ."3. Copy the 6-digit code (valid for 10 minutes).\n"
                ."4. Send it here: <code>/link 123456</code>"
            );

            return;
        }

        $code = trim($args[0]);
        $userId = $this->linkCodeService->consume($code);

        if ($userId === null) {
            $this->telegram->sendMessage(
                $chatId,
                "❌ <b>Invalid or expired code.</b>\n"
                ."Codes expire after 10 minutes. Generate a fresh one in the web app and try again."
            );

            return;
        }

        $user = User::find($userId);

        if ($user === null) {
            $this->telegram->sendMessage(
                $chatId,
                "❌ <b>Account not found.</b>\n"
                ."Something went wrong on our end. Please generate a new code and try again."
            );

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

        $this->telegram->sendMessage(
            $chatId,
            "🎉 <b>Linked!</b>\n"
            ."You're now connected to <code>{$safeEmail}</code>.\n\n"
            ."<b>What you can do now:</b>\n"
            ."• Share a contact to save it as a favorite.\n"
            ."• Send menu photos — they'll sync to your account.\n"
            ."• Open <code>/settings</code> to review your link.",
            [
                'reply_markup' => [
                    'inline_keyboard' => [[
                        ['text' => '⚙️ Settings', 'callback_data' => 'nav:settings'],
                        ['text' => '🏠 Main menu', 'callback_data' => 'nav:start'],
                    ]],
                ],
            ]
        );
    }
}
