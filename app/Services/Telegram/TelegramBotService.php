<?php

namespace App\Services\Telegram;

use App\Exceptions\Telegram\TelegramApiException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramBotService
{
    private function baseUrl(): string
    {
        $base = config('services.telegram.bot_api_base', 'https://api.telegram.org');
        $token = config('services.telegram.bot_token');

        return rtrim((string) $base, '/').'/bot'.$token;
    }

    /**
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     *
     * @throws TelegramApiException
     */
    private function call(string $method, array $params = []): array
    {
        try {
            $response = Http::timeout(10)
                ->retry(2, 200, fn (mixed $exception) => $exception instanceof RequestException && $exception->response->serverError())
                ->post("{$this->baseUrl()}/{$method}", $params);
        } catch (RequestException $e) {
            throw new TelegramApiException(
                "Telegram API call failed [{$method}]: HTTP {$e->response->status()} – {$e->response->body()}",
                0,
                $e
            );
        }

        /** @var array<string,mixed> $decoded */
        $decoded = $response->json();

        if (! ($decoded['ok'] ?? false)) {
            throw new TelegramApiException(
                "Telegram API error [{$method}]: ".($decoded['description'] ?? 'unknown error')
            );
        }

        return $decoded;
    }

    /**
     * @param  array<string,mixed>  $params
     */
    private function safeSend(string $method, array $params = []): void
    {
        try {
            $this->call($method, $params);
        } catch (TelegramApiException $e) {
            Log::error('TelegramBotService send failed', [
                'method' => $method,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send a text message.
     *
     * @param  array<string,mixed>  $extra
     */
    public function sendMessage(int|string $chatId, string $text, array $extra = []): void
    {
        $this->safeSend('sendMessage', array_merge([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ], $extra));
    }

    /**
     * Send a venue.
     */
    public function sendVenue(
        int|string $chatId,
        float $latitude,
        float $longitude,
        string $title,
        string $address
    ): void {
        $this->safeSend('sendVenue', [
            'chat_id' => $chatId,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'title' => $title,
            'address' => $address,
        ]);
    }

    /**
     * Send a location.
     */
    public function sendLocation(int|string $chatId, float $latitude, float $longitude): void
    {
        $this->safeSend('sendLocation', [
            'chat_id' => $chatId,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);
    }

    /**
     * Send a photo.
     *
     * @param  array<string,mixed>  $extra
     */
    public function sendPhoto(int|string $chatId, string $photo, array $extra = []): void
    {
        $this->safeSend('sendPhoto', array_merge([
            'chat_id' => $chatId,
            'photo' => $photo,
        ], $extra));
    }

    /**
     * Send a video.
     *
     * @param  array<string,mixed>  $extra
     */
    public function sendVideo(int|string $chatId, string $video, array $extra = []): void
    {
        $this->safeSend('sendVideo', array_merge([
            'chat_id' => $chatId,
            'video' => $video,
        ], $extra));
    }

    /**
     * Send a contact.
     */
    public function sendContact(
        int|string $chatId,
        string $phoneNumber,
        string $firstName,
        string $lastName = ''
    ): void {
        $params = [
            'chat_id' => $chatId,
            'phone_number' => $phoneNumber,
            'first_name' => $firstName,
        ];

        if ($lastName !== '') {
            $params['last_name'] = $lastName;
        }

        $this->safeSend('sendContact', $params);
    }

    /**
     * Send a chat action (typing, upload_photo, etc).
     */
    public function sendChatAction(int|string $chatId, string $action = 'typing'): void
    {
        $this->safeSend('sendChatAction', [
            'chat_id' => $chatId,
            'action' => $action,
        ]);
    }

    /**
     * Answer a callback query.
     *
     * @param  array<string,mixed>  $extra
     */
    public function answerCallbackQuery(string $callbackQueryId, array $extra = []): void
    {
        $this->safeSend('answerCallbackQuery', array_merge([
            'callback_query_id' => $callbackQueryId,
        ], $extra));
    }

    /**
     * Edit a message's text.
     *
     * @param  array<string,mixed>  $extra
     */
    public function editMessageText(
        int|string $chatId,
        int $messageId,
        string $text,
        array $extra = []
    ): void {
        $this->safeSend('editMessageText', array_merge([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ], $extra));
    }

    /**
     * Set the webhook.
     *
     * @param  list<string>  $allowedUpdates
     * @return array<string,mixed>
     *
     * @throws TelegramApiException
     */
    public function setWebhook(
        string $url,
        string $secretToken = '',
        array $allowedUpdates = []
    ): array {
        $params = ['url' => $url];

        if ($secretToken !== '') {
            $params['secret_token'] = $secretToken;
        }

        if ($allowedUpdates !== []) {
            $params['allowed_updates'] = $allowedUpdates;
        }

        return $this->call('setWebhook', $params);
    }

    /**
     * Delete the webhook.
     *
     * @return array<string,mixed>
     *
     * @throws TelegramApiException
     */
    public function deleteWebhook(): array
    {
        return $this->call('deleteWebhook');
    }

    /**
     * Get webhook info.
     *
     * @return array<string,mixed>
     *
     * @throws TelegramApiException
     */
    public function getWebhookInfo(): array
    {
        return $this->call('getWebhookInfo');
    }

    /**
     * Get bot info.
     *
     * @return array<string,mixed>
     *
     * @throws TelegramApiException
     */
    public function getMe(): array
    {
        return $this->call('getMe');
    }

    /**
     * Get file info.
     *
     * @return array<string,mixed>
     *
     * @throws TelegramApiException
     */
    public function getFile(string $fileId): array
    {
        return $this->call('getFile', ['file_id' => $fileId]);
    }
}
