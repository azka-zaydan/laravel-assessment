<?php

namespace App\Services\Telegram\Commands;

use App\Models\TelegramUser;
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
        $isLinked = TelegramUser::where('chat_id', (string) $chatId)->exists();

        $accountStatus = $isLinked
            ? '✅ <b>Linked</b> — favorites save to your profile.'
            : '🔒 <b>Not linked</b> — run <code>/link &lt;code&gt;</code> to enable favorites.';

        $text = "❓ <b>Help &amp; Commands</b>\n"
            ."Everything this bot can do, in one place.\n\n"

            ."📖 <b>Commands</b>\n"
            ."• <code>/start</code> — welcome screen &amp; quick actions\n"
            ."• <code>/help</code> — show this guide\n"
            ."• <code>/search &lt;query&gt;</code> — find restaurants by name or cuisine\n"
            ."   <i>Example:</i> <code>/search ramen</code>\n"
            ."• <code>/nearby</code> — request your location to find spots around you\n"
            ."• <code>/link &lt;6-digit-code&gt;</code> — connect your app account\n"
            ."• <code>/settings</code> — view account &amp; preferences\n"
            ."• <code>/cancel</code> — abort the current flow\n\n"

            ."⚡ <b>Quick actions (no command needed)</b>\n"
            ."• 📍 Share your <b>location</b> → nearby restaurants\n"
            ."• 👤 Share a <b>contact</b> → save it as a favorite\n"
            ."• 📷 Send a <b>photo</b> of a menu → we'll extract the items\n"
            ."• 🎥 Send a <b>video</b> clip → attached to your submissions\n\n"

            ."👤 <b>Your account</b>\n"
            .$accountStatus."\n\n"

            ."<blockquote expandable>💡 <b>Pro tips</b> (tap to expand)\n"
            ."• Use the keyboard buttons at the bottom — they're faster than typing.\n"
            ."• After a search, tap <b>See menu</b> or <b>See reviews</b> on any result.\n"
            ."• Reviews paginate 5-at-a-time — use « Previous / Next » to browse.\n"
            ."• Location sharing is one-time — we don't track you between messages.\n"
            ."• Stuck mid-flow? <code>/cancel</code> exits cleanly and returns to the menu.\n"
            ."• Your chat stays private: photos &amp; contacts only save when you act on them.</blockquote>\n\n"

            .'🆘 <i>Something broken? Type <code>/cancel</code> then <code>/start</code> to reset.</i>';

        $inlineKeyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🔍 Try a search', 'callback_data' => 'nav:search'],
                    ['text' => '📍 Find nearby', 'callback_data' => 'nav:nearby'],
                ],
                [
                    ['text' => '⚙️ Settings', 'callback_data' => 'nav:settings'],
                    ['text' => '🏠 Main menu', 'callback_data' => 'nav:start'],
                ],
            ],
        ];

        $this->telegram->sendMessage($chatId, $text, ['reply_markup' => $inlineKeyboard]);
    }
}
