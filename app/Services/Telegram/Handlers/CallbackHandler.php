<?php

namespace App\Services\Telegram\Handlers;

use App\Services\Restaurants\RestaurantService;
use App\Services\Telegram\Commands\HelpCommand;
use App\Services\Telegram\Commands\NearbyCommand;
use App\Services\Telegram\Commands\SettingsCommand;
use App\Services\Telegram\Commands\StartCommand;
use App\Services\Telegram\TelegramBotService;
use Illuminate\Contracts\Container\Container;

class CallbackHandler implements MessageHandler
{
    public function __construct(
        private readonly TelegramBotService $telegram,
        private readonly RestaurantService $restaurantService,
        private readonly Container $container,
    ) {}

    /**
     * @param  array<string,mixed>  $update
     */
    public function handle(array $update): void
    {
        $callbackQuery = $update['callback_query'] ?? [];
        $callbackQueryId = (string) ($callbackQuery['id'] ?? '');
        $chatId = $callbackQuery['message']['chat']['id'] ?? null;
        $data = (string) ($callbackQuery['data'] ?? '');

        if ($chatId === null) {
            return;
        }

        if (preg_match('/^nav:(start|help|search|nearby|settings)$/', $data, $matches)) {
            $this->handleNav($callbackQueryId, $callbackQuery, $matches[1]);

            return;
        }

        if (preg_match('/^menu:(\d+)$/', $data, $matches)) {
            $this->handleMenu($callbackQueryId, $chatId, (int) $matches[1]);

            return;
        }

        if (preg_match('/^rev:(\d+):p(\d+)$/', $data, $matches)) {
            $this->handleReviews($callbackQueryId, $chatId, (int) $matches[1], (int) $matches[2]);

            return;
        }

        $this->telegram->answerCallbackQuery($callbackQueryId, ['text' => 'Unknown action.']);
    }

    /**
     * Route the inline-keyboard nav buttons (Main menu / Help / Settings / ...)
     * through the same commands that handle slash-text, so the UX is consistent.
     *
     * @param  array<string,mixed>  $callbackQuery
     */
    private function handleNav(string $callbackQueryId, array $callbackQuery, string $target): void
    {
        $this->telegram->answerCallbackQuery($callbackQueryId);

        $message = $callbackQuery['message'] ?? [];
        // The callback_query.message has no "from" block for the user, only the bot.
        // Inject the real user so commands like /start can greet them by name.
        $message['from'] = $callbackQuery['from'] ?? [];

        switch ($target) {
            case 'start':
                $this->container->make(StartCommand::class)->handle($message, []);
                break;
            case 'help':
                $this->container->make(HelpCommand::class)->handle($message, []);
                break;
            case 'nearby':
                $this->container->make(NearbyCommand::class)->handle($message, []);
                break;
            case 'settings':
                $this->container->make(SettingsCommand::class)->handle($message, []);
                break;
            case 'search':
                $chatId = $message['chat']['id'] ?? null;
                if ($chatId !== null) {
                    $this->telegram->sendMessage(
                        $chatId,
                        "🔍 <b>Search restaurants</b>\n"
                        ."Send <code>/search &lt;query&gt;</code> — search by name or cuisine.\n\n"
                        ."<b>Examples</b>\n"
                        ."• <code>/search ramen</code>\n"
                        ."• <code>/search vegan pizza</code>\n"
                        ."• <code>/search cafe near central</code>"
                    );
                }
                break;
        }
    }

    private function handleMenu(string $callbackQueryId, int|string $chatId, int $restaurantId): void
    {
        $this->telegram->answerCallbackQuery($callbackQueryId);
        $this->telegram->sendChatAction($chatId, 'typing');

        $menuItems = $this->restaurantService->getDailyMenu($restaurantId);

        if ($menuItems === []) {
            $this->telegram->sendMessage(
                $chatId,
                "📭 <b>No menu available</b> for this restaurant yet.\n"
                ."Try the reviews tab or search for something else."
            );

            return;
        }

        $itemCount = count($menuItems);
        $lines = ["🍽️ <b>Daily menu</b> — {$itemCount} items\n"];

        foreach ($menuItems as $item) {
            $name = htmlspecialchars((string) $item['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $price = htmlspecialchars((string) $item['price'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            $line = "• <b>{$name}</b> — <code>{$price}</code>";

            $desc = $item['description'] ?? null;
            if ($desc !== null && $desc !== '') {
                $safeDesc = htmlspecialchars((string) $desc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                // Expandable blockquote keeps long descriptions out of the way.
                $line .= "\n<blockquote expandable><i>{$safeDesc}</i></blockquote>";
            }

            $lines[] = $line;
        }

        $this->telegram->sendMessage($chatId, implode("\n\n", $lines), [
            'reply_markup' => [
                'inline_keyboard' => [[
                    ['text' => '⭐ See reviews', 'callback_data' => "rev:{$restaurantId}:p1"],
                    ['text' => '🏠 Main menu', 'callback_data' => 'nav:start'],
                ]],
            ],
        ]);
    }

    private function handleReviews(
        string $callbackQueryId,
        int|string $chatId,
        int $restaurantId,
        int $page
    ): void {
        $this->telegram->answerCallbackQuery($callbackQueryId);
        $this->telegram->sendChatAction($chatId, 'typing');

        $perPage = 5;
        $start = ($page - 1) * $perPage;
        $results = $this->restaurantService->getReviews($restaurantId, $start, $perPage);

        $reviews = $results['reviews'];
        $total = $results['total'];

        if ($reviews === []) {
            $this->telegram->sendMessage(
                $chatId,
                "💬 <b>No reviews available</b> for this restaurant yet.\n"
                ."Be the first to try it!"
            );

            return;
        }

        $totalPages = (int) max(1, ceil($total / $perPage));
        $lines = ["⭐ <b>Reviews</b> — page {$page}/{$totalPages} ({$total} total)\n"];

        $index = $start + 1;
        foreach ($reviews as $review) {
            $r = $review['review'] ?? $review;
            $reviewText = htmlspecialchars((string) ($r['review_text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $ratingRaw = (string) ($r['rating']['title'] ?? $r['rating'] ?? '');
            $rating = htmlspecialchars($ratingRaw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $userName = htmlspecialchars((string) ($r['user']['name'] ?? 'Anonymous'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            $stars = $this->ratingToStars($ratingRaw);

            $lines[] = "<b>{$index}.</b> {$stars} <b>{$userName}</b>"
                .($rating !== '' ? " — <i>{$rating}</i>" : '')
                ."\n{$reviewText}";
            $index++;
        }

        $navButtons = [];
        if ($page > 1) {
            $navButtons[] = ['text' => '« Previous', 'callback_data' => "rev:{$restaurantId}:p".($page - 1)];
        }

        if ($start + $perPage < $total) {
            $navButtons[] = ['text' => 'Next »', 'callback_data' => "rev:{$restaurantId}:p".($page + 1)];
        }

        // Always offer a way out — Telegram UX rule: "never leave the user without an escape".
        $actionRow = [
            ['text' => '🍽️ See menu', 'callback_data' => "menu:{$restaurantId}"],
            ['text' => '🏠 Main menu', 'callback_data' => 'nav:start'],
        ];

        $keyboardRows = [];
        if ($navButtons !== []) {
            $keyboardRows[] = $navButtons;
        }
        $keyboardRows[] = $actionRow;

        $this->telegram->sendMessage(
            $chatId,
            implode("\n\n", $lines),
            ['reply_markup' => ['inline_keyboard' => $keyboardRows]]
        );
    }

    /**
     * Best-effort star rendering from a rating string. Accepts numerics like
     * "4.3", "4", or descriptive titles like "Very Good" (falls back to empty).
     */
    private function ratingToStars(string $rating): string
    {
        if ($rating === '' || ! is_numeric($rating)) {
            return '';
        }

        $score = (float) $rating;
        // Ratings are typically on a 0-5 scale; round to nearest int for star count.
        $filled = (int) round(max(0.0, min(5.0, $score)));
        $empty = 5 - $filled;

        return str_repeat('⭐', $filled).str_repeat('☆', $empty);
    }
}
