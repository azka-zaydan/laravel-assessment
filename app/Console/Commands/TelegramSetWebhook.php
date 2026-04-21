<?php

namespace App\Console\Commands;

use App\Exceptions\Telegram\TelegramApiException;
use App\Services\Telegram\TelegramBotService;
use Illuminate\Console\Command;

class TelegramSetWebhook extends Command
{
    protected $signature = 'telegram:set-webhook';

    protected $description = 'Register the Telegram webhook with the Bot API';

    public function handle(TelegramBotService $telegram): int
    {
        $url = (string) config('services.telegram.webhook_url');
        $secret = (string) config('services.telegram.webhook_secret');

        $this->info("Setting webhook to: {$url}");

        try {
            $response = $telegram->setWebhook(
                url: $url,
                secretToken: $secret,
                allowedUpdates: ['message', 'callback_query'],
            );

            $this->info('Response: '.json_encode($response));

            return self::SUCCESS;
        } catch (TelegramApiException $e) {
            $this->error('Failed to set webhook: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
