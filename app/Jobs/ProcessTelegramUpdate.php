<?php

namespace App\Jobs;

use App\Services\Telegram\MessageDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessTelegramUpdate implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 90;

    /**
     * @param  array<string,mixed>  $update
     */
    public function __construct(public readonly array $update) {}

    public function handle(MessageDispatcher $dispatcher): void
    {
        $dispatcher->dispatch($this->update);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('ProcessTelegramUpdate job failed', [
            'update_id' => $this->update['update_id'] ?? null,
            'error' => $exception->getMessage(),
        ]);
    }
}
