<?php

namespace App\Services\Telegram\Commands;

use App\Models\TelegramUser;
use App\Services\Telegram\TelegramBotService;

class SettingsCommand implements Command
{
    public function __construct(private readonly TelegramBotService $telegram) {}

    /**
     * @param  array<string,mixed>  $message
     * @param  string[]  $args
     */
    public function handle(array $message, array $args): void
    {
        $chatId = $message['chat']['id'];

        $telegramUser = TelegramUser::with('user')->where('chat_id', (string) $chatId)->first();

        if ($telegramUser === null) {
            $text = "⚙️ <b>Settings</b>\n\n"
                ."🔒 <b>Account:</b> not linked\n"
                ."<i>Link your app account to save favorites and sync across devices.</i>\n\n"
                .'Generate a code in the web app, then send <code>/link &lt;code&gt;</code> here.';

            $this->telegram->sendMessage($chatId, $text, [
                'reply_markup' => [
                    'inline_keyboard' => [[
                        ['text' => '🏠 Main menu', 'callback_data' => 'nav:start'],
                        ['text' => '❓ Help', 'callback_data' => 'nav:help'],
                    ]],
                ],
            ]);

            return;
        }

        $email = htmlspecialchars((string) ($telegramUser->user->email ?? 'unknown'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $lang = htmlspecialchars((string) ($telegramUser->language_code ?? 'auto'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $linkedAt = $telegramUser->created_at?->diffForHumans() ?? 'recently';

        $text = "⚙️ <b>Settings</b>\n\n"
            ."✅ <b>Account:</b> linked\n"
            ."📧 <b>Email:</b> <code>{$email}</code>\n"
            ."🌐 <b>Language:</b> <code>{$lang}</code>\n"
            ."🕒 <b>Linked:</b> {$linkedAt}\n\n"
            ."<blockquote>🔐 Your chat ID is never shared publicly. You can unlink at any time by revoking access in the web app.</blockquote>";

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
