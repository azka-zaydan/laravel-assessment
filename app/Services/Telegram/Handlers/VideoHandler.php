<?php

namespace App\Services\Telegram\Handlers;

use App\Models\UserSubmission;
use App\Services\Telegram\TelegramBotService;

class VideoHandler implements MessageHandler
{
    public function __construct(private readonly TelegramBotService $telegram) {}

    /**
     * @param  array<string,mixed>  $update
     */
    public function handle(array $update): void
    {
        $message = $update['message'] ?? [];
        $chatId = $message['chat']['id'] ?? null;
        $video = $message['video'] ?? [];

        if ($chatId === null) {
            return;
        }

        $fileId = (string) ($video['file_id'] ?? '');
        $messageId = (int) ($message['message_id'] ?? 0);

        UserSubmission::create([
            'chat_id' => (string) $chatId,
            'type' => 'video',
            'file_id' => $fileId,
            'message_id' => $messageId,
            'raw_update' => $update,
        ]);

        $this->telegram->sendMessage(
            $chatId,
            "🎥 <b>Got your video!</b>\n"
            ."Saved to your submissions. Thanks for contributing!"
        );
    }
}
