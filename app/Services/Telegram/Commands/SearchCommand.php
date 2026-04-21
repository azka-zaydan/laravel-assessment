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
            $this->telegram->sendMessage($chatId, 'Usage: /search &lt;query&gt;');

            return;
        }

        $query = trim($args[0]);
        $results = $this->restaurantService->search(['q' => $query, 'count' => 5]);

        $restaurants = $results['restaurants'];

        if ($restaurants === []) {
            $safeQuery = htmlspecialchars($query, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $this->telegram->sendMessage($chatId, "No restaurants found for <b>{$safeQuery}</b>.");

            return;
        }

        /** @var list<list<array{text:string,callback_data:string}>> $inlineRows */
        $inlineRows = [];

        foreach ($restaurants as $restaurant) {
            $r = $restaurant['restaurant'] ?? $restaurant;

            $location = $r['location'] ?? [];
            // Support both normalized (lat/lon) and raw Zomato (latitude/longitude) formats
            $lat = (float) ($location['lat'] ?? $location['latitude'] ?? 0);
            $lon = (float) ($location['lon'] ?? $location['longitude'] ?? 0);
            $name = (string) ($r['name'] ?? 'Unknown');
            $address = (string) ($r['address'] ?? $location['address'] ?? '');
            $id = (int) ($r['id'] ?? 0);

            $this->telegram->sendVenue($chatId, $lat, $lon, $name, $address);

            $inlineRows[] = [
                ['text' => 'See menu', 'callback_data' => "menu:{$id}"],
                ['text' => 'See reviews', 'callback_data' => "rev:{$id}:p1"],
            ];
        }

        $inlineKeyboard = ['inline_keyboard' => $inlineRows];
        $this->telegram->sendMessage(
            $chatId,
            'Select an action for a restaurant:',
            ['reply_markup' => $inlineKeyboard]
        );
    }
}
