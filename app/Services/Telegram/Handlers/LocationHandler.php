<?php

namespace App\Services\Telegram\Handlers;

use App\Services\Restaurants\RestaurantService;
use App\Services\Telegram\TelegramBotService;

class LocationHandler implements MessageHandler
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
        $message = $update['message'] ?? [];
        $chatId = $message['chat']['id'] ?? null;
        $location = $message['location'] ?? [];

        if ($chatId === null) {
            return;
        }

        $lat = (float) ($location['latitude'] ?? 0);
        $lon = (float) ($location['longitude'] ?? 0);

        $results = $this->restaurantService->getNearby($lat, $lon, 5);
        $restaurants = $results['restaurants'];

        if ($restaurants === []) {
            $this->telegram->sendMessage($chatId, 'No nearby restaurants found.');

            return;
        }

        foreach ($restaurants as $restaurant) {
            $r = $restaurant['restaurant'] ?? $restaurant;

            $location = $r['location'] ?? [];
            // Support both normalized (lat/lon) and raw Zomato (latitude/longitude) formats
            $rLat = (float) ($location['lat'] ?? $location['latitude'] ?? 0);
            $rLon = (float) ($location['lon'] ?? $location['longitude'] ?? 0);
            $name = (string) ($r['name'] ?? 'Unknown');
            $address = (string) ($r['address'] ?? $location['address'] ?? '');
            $id = (int) ($r['id'] ?? 0);

            $this->telegram->sendVenue($chatId, $rLat, $rLon, $name, $address);

            $inlineKeyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => 'See menu', 'callback_data' => "menu:{$id}"],
                        ['text' => 'See reviews', 'callback_data' => "rev:{$id}:p1"],
                    ],
                ],
            ];

            $safeName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $this->telegram->sendMessage(
                $chatId,
                "Actions for <b>{$safeName}</b>:",
                ['reply_markup' => $inlineKeyboard]
            );
        }
    }
}
