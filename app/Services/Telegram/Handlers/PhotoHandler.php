<?php

namespace App\Services\Telegram\Handlers;

use App\Jobs\ProcessPhotoSubmission;
use App\Models\UserSubmission;
use App\Services\Telegram\TelegramBotService;

class PhotoHandler implements MessageHandler
{
    public function __construct(private readonly TelegramBotService $telegram) {}

    /**
     * @param  array<string,mixed>  $update
     */
    public function handle(array $update): void
    {
        $message = $update['message'] ?? [];
        $chatId = $message['chat']['id'] ?? null;
        $photos = $message['photo'] ?? [];

        if ($chatId === null) {
            return;
        }

        // Pick the largest photo (last in the array).
        $largestPhoto = end($photos);

        if ($largestPhoto === false) {
            $this->telegram->sendMessage(
                $chatId,
                "⚠️ <b>Couldn't read that photo.</b>\n"
                .'Try sending it again, or send a clearer image of the menu.'
            );

            return;
        }

        $fileId = (string) ($largestPhoto['file_id'] ?? '');
        $messageId = (int) ($message['message_id'] ?? 0);

        $submission = UserSubmission::create([
            'chat_id' => (string) $chatId,
            'type' => 'photo',
            'file_id' => $fileId,
            'message_id' => $messageId,
            'raw_update' => $update,
        ]);

        ProcessPhotoSubmission::dispatch($submission->id, (string) $chatId);

        $this->telegram->sendMessage(
            $chatId,
            "📷 <b>Got your photo!</b>\n"
            ."I'm processing the menu in the background — this usually takes a few seconds. "
            ."You'll get a message here as soon as it's done.\n\n"
            .'<i>Keep using the bot in the meantime:</i> <code>/search</code> or <code>/nearby</code>.'
        );
    }
}
