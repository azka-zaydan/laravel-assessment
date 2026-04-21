<?php

namespace App\Services\Telegram\Handlers;

use App\Services\Restaurants\RestaurantService;
use App\Services\Telegram\TelegramBotService;

class CallbackHandler implements MessageHandler
{
    public function __construct(
        private readonly TelegramBotService $telegram,
        private readonly RestaurantService $restaurantService,
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

    private function handleMenu(string $callbackQueryId, int|string $chatId, int $restaurantId): void
    {
        $this->telegram->answerCallbackQuery($callbackQueryId);

        $menuItems = $this->restaurantService->getDailyMenu($restaurantId);

        if ($menuItems === []) {
            $this->telegram->sendMessage($chatId, 'No menu available for this restaurant.');

            return;
        }

        $lines = ["<b>Daily Menu</b>\n"];
        foreach ($menuItems as $item) {
            $name = htmlspecialchars($item['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $price = htmlspecialchars($item['price'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            $line = "<b>{$name}</b> — {$price}";

            if ($item['description'] !== null && $item['description'] !== '') {
                $desc = htmlspecialchars($item['description'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $line .= "\n<i>{$desc}</i>";
            }

            $lines[] = $line;
        }

        $this->telegram->sendMessage($chatId, implode("\n\n", $lines));
    }

    private function handleReviews(
        string $callbackQueryId,
        int|string $chatId,
        int $restaurantId,
        int $page
    ): void {
        $this->telegram->answerCallbackQuery($callbackQueryId);

        $perPage = 5;
        $start = ($page - 1) * $perPage;
        $results = $this->restaurantService->getReviews($restaurantId, $start, $perPage);

        $reviews = $results['reviews'];
        $total = $results['total'];

        if ($reviews === []) {
            $this->telegram->sendMessage($chatId, 'No reviews available.');

            return;
        }

        $lines = ["<b>Reviews (Page {$page})</b>\n"];
        foreach ($reviews as $review) {
            $r = $review['review'] ?? $review;
            $reviewText = htmlspecialchars((string) ($r['review_text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $rating = htmlspecialchars((string) ($r['rating']['title'] ?? $r['rating'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $userName = htmlspecialchars((string) ($r['user']['name'] ?? 'Anonymous'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            $lines[] = "<b>{$userName}</b> — <i>{$rating}</i>\n{$reviewText}";
        }

        $navButtons = [];
        if ($page > 1) {
            $navButtons[] = ['text' => '« Previous', 'callback_data' => "rev:{$restaurantId}:p".($page - 1)];
        }

        if ($start + $perPage < $total) {
            $navButtons[] = ['text' => 'Next »', 'callback_data' => "rev:{$restaurantId}:p".($page + 1)];
        }

        $extra = [];
        if ($navButtons !== []) {
            $extra['reply_markup'] = ['inline_keyboard' => [$navButtons]];
        }

        $this->telegram->sendMessage($chatId, implode("\n\n", $lines), $extra);
    }
}
