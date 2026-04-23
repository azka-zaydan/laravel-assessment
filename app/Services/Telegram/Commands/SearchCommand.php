<?php

namespace App\Services\Telegram\Commands;

use App\Services\Restaurants\RestaurantService;
use App\Services\Telegram\TelegramBotService;

class SearchCommand implements Command
{
    public function __construct(
        private readonly TelegramBotService $telegram,
        private readonly RestaurantService $restaurantService,
    ) {}

    /**
     * @param  array<string,mixed>  $message
     * @param  string[]  $args
     */
    public function handle(array $message, array $args): void
    {
        $chatId = $message['chat']['id'];

        if ($args === [] || trim($args[0]) === '') {
            $this->telegram->sendMessage(
                $chatId,
                "🔍 <b>Search restaurants</b>\n"
                ."Usage: <code>/search &lt;query&gt;</code>\n\n"
                ."<b>Examples</b>\n"
                ."• <code>/search ramen</code>\n"
                ."• <code>/search vegan pizza</code>\n"
                ."• <code>/search cafe central</code>\n\n"
                ."<i>Or tap 📍 Nearby to find spots around you.</i>"
            );

            return;
        }

        $query = trim($args[0]);
        $safeQuery = htmlspecialchars($query, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // Give the user a "typing..." status while we hit the provider.
        $this->telegram->sendChatAction($chatId, 'typing');

        $results = $this->restaurantService->search(['q' => $query, 'count' => 5]);

        $restaurants = $results['restaurants'];

        if ($restaurants === []) {
            $this->telegram->sendMessage(
                $chatId,
                "😕 <b>No restaurants found for</b> <code>{$safeQuery}</code>\n"
                ."Try a different keyword, or share your location for spots nearby.",
                [
                    'reply_markup' => [
                        'inline_keyboard' => [[
                            ['text' => '📍 Try nearby', 'callback_data' => 'nav:nearby'],
                            ['text' => '❓ Help', 'callback_data' => 'nav:help'],
                        ]],
                    ],
                ]
            );

            return;
        }

        $count = count($restaurants);

        // One framing message before the venue cards, so users know what's coming.
        $this->telegram->sendMessage(
            $chatId,
            "🔍 <b>Results for</b> <code>{$safeQuery}</code> — {$count} match".($count === 1 ? '' : 'es')
        );

        $index = 1;
        foreach ($restaurants as $restaurant) {
            $r = $restaurant['restaurant'] ?? $restaurant;

            $location = $r['location'] ?? [];
            // Support both normalized (lat/lon) and raw Zomato (latitude/longitude) formats.
            $lat = (float) ($location['lat'] ?? $location['latitude'] ?? 0);
            $lon = (float) ($location['lon'] ?? $location['longitude'] ?? 0);
            $name = (string) ($r['name'] ?? 'Unknown');
            $address = (string) ($r['address'] ?? $location['address'] ?? '');
            $id = (int) ($r['id'] ?? 0);

            $this->telegram->sendVenue($chatId, $lat, $lon, $name, $address);

            $safeName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $this->telegram->sendMessage(
                $chatId,
                "<b>{$index}.</b> {$safeName}",
                [
                    'reply_markup' => [
                        'inline_keyboard' => [[
                            ['text' => '🍽️ Menu', 'callback_data' => "menu:{$id}"],
                            ['text' => '⭐ Reviews', 'callback_data' => "rev:{$id}:p1"],
                        ]],
                    ],
                ]
            );

            $index++;
        }
    }
}
