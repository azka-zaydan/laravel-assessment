<?php

namespace App\Jobs;

use App\Models\UserSubmission;
use App\Services\Telegram\TelegramBotService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessPhotoSubmission implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public readonly int $submissionId,
        public readonly string $chatId,
    ) {}

    public function handle(TelegramBotService $telegram): void
    {
        // Stub OCR: simulate processing delay
        sleep(1);

        $telegram->sendMessage($this->chatId, 'OCR processing complete. Menu items extracted successfully.');

        UserSubmission::where('id', $this->submissionId)->update([
            'processed_at' => now(),
        ]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('ProcessPhotoSubmission job failed', [
            'submission_id' => $this->submissionId,
            'chat_id' => $this->chatId,
            'error' => $exception->getMessage(),
        ]);
    }
}
