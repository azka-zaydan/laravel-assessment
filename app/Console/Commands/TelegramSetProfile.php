<?php

namespace App\Console\Commands;

use App\Exceptions\Telegram\TelegramApiException;
use App\Services\Telegram\TelegramBotService;
use Illuminate\Console\Command;

class TelegramSetProfile extends Command
{
    protected $signature = 'telegram:set-profile
        {--name= : Override the bot display name}
        {--skip-name : Skip setting the bot name (useful mid-quota, name changes are rate-limited)}';

    protected $description = 'Register the bot commands, description, short description, and (optionally) name with the Telegram Bot API.';

    /**
     * The public command list shown when users type "/" in chat.
     *
     * Telegram convention: command name lowercase (no leading slash),
     * description under ~60 chars for readability on mobile.
     *
     * @var list<array{command:string,description:string}>
     */
    private const COMMANDS = [
        ['command' => 'start', 'description' => '🏠 Welcome & quick actions'],
        ['command' => 'search', 'description' => '🔍 Search restaurants by name or cuisine'],
        ['command' => 'nearby', 'description' => '📍 Find restaurants around you'],
        ['command' => 'help', 'description' => '❓ Full command list and tips'],
        ['command' => 'link', 'description' => '🔗 Link your web account (6-digit code)'],
        ['command' => 'settings', 'description' => '⚙️ View account status & preferences'],
        ['command' => 'cancel', 'description' => '🛑 Abort the current flow'],
    ];

    /**
     * Long description — shown in the "What can this bot do?" panel that
     * appears above the chat before the user taps Start. Up to 512 chars.
     */
    private const DESCRIPTION = <<<'TXT'
🍽️ Your pocket restaurant concierge.

Discover spots nearby, search by cuisine, peek at menus, and read reviews — all without leaving Telegram.

• /search <query> — find by name or cuisine
• /nearby — share your location for nearby spots
• /link — connect your web account to save favorites
• /help — full guide & tips

Your location is never stored. Chats stay private.
TXT;

    /**
     * Short description — appears on the bot's profile card and is included
     * when users share the bot link. Up to 120 chars.
     */
    private const SHORT_DESCRIPTION = 'Discover restaurants, browse menus, and read reviews — right inside Telegram.';

    /**
     * Default name — used unless --name is passed. Leave unchanged in CI to
     * avoid Telegram's name-change rate limit (2 per hour).
     */
    private const DEFAULT_NAME = 'Restaurant Concierge';

    public function handle(TelegramBotService $telegram): int
    {
        $this->line('');
        $this->info('Registering bot profile with Telegram…');
        $this->line('');

        $steps = [
            'commands' => fn (): array => $telegram->setMyCommands(self::COMMANDS),
            'description' => fn (): array => $telegram->setMyDescription(self::DESCRIPTION),
            'short description' => fn (): array => $telegram->setMyShortDescription(self::SHORT_DESCRIPTION),
        ];

        if (! $this->option('skip-name')) {
            $name = (string) ($this->option('name') ?: self::DEFAULT_NAME);
            $steps['name'] = fn (): array => $telegram->setMyName($name);
        }

        $failures = 0;
        foreach ($steps as $label => $step) {
            try {
                $step();
                $this->line("  ✓ {$label}");
            } catch (TelegramApiException $e) {
                $failures++;
                $this->error("  ✗ {$label}: ".$e->getMessage());
            }
        }

        $this->line('');

        if ($failures > 0) {
            $this->error("Completed with {$failures} failure(s).");

            return self::FAILURE;
        }

        $this->info('Bot profile registered successfully.');
        $this->line('Tip: send /start to your bot to see the new welcome screen.');

        return self::SUCCESS;
    }
}
