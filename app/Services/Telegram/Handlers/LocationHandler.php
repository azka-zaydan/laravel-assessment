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

        // Visual feedback while we hit the restaurant provider. "find_location"
        // is the Telegram-native status that matches this operation.
        $this->telegram->sendChatAction($chatId, 'find_location');

        $lat = (float) ($location['latitude'] ?? 0);
        $lon = (float) ($location['longitude'] ?? 0);

        $results = $this->restaurantService->getNearby($lat, $lon, 5);
        $restaurants = $results['restaurants'];

        if ($restaurants === []) {
            $this->telegram->sendMessage(
                $chatId,
                "😕 <b>No nearby restaurants found.</b>\n"
                .'Try <code>/search &lt;cuisine&gt;</code> or share a location with more coverage.',
                [
                    'reply_markup' => [
                        'inline_keyboard' => [[
                            ['text' => '🔍 Search instead', 'callback_data' => 'nav:search'],
                            ['text' => '❓ Help', 'callback_data' => 'nav:help'],
                        ]],
                    ],
                ]
            );

            return;
        }

        $count = count($restaurants);

        // Intro: one message framing the list so venues that follow feel grouped.
        $this->telegram->sendMessage(
            $chatId,
            "📍 <b>Found {$count} spots near you</b>\n"
            .'<i>Tap any venue to open it in Maps, or use the buttons below each card.</i>'
        );

        /** @var list<list<array{text:string,callback_data:string}>> $inlineRows */
        $inlineRows = [];
        $index = 1;

        foreach ($restaurants as $restaurant) {
            $r = $restaurant['restaurant'] ?? $restaurant;

            $location = $r['location'] ?? [];
            $rLat = (float) ($location['lat'] ?? $location['latitude'] ?? 0);
            $rLon = (float) ($location['lon'] ?? $location['longitude'] ?? 0);
            $name = (string) ($r['name'] ?? 'Unknown');
            $address = (string) ($r['address'] ?? $location['address'] ?? '');
            $id = (int) ($r['id'] ?? 0);

            $this->telegram->sendVenue($chatId, $rLat, $rLon, $name, $address);

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
