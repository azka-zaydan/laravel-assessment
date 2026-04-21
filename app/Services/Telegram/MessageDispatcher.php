<?php

namespace App\Services\Telegram;

use App\Services\Telegram\Handlers\CallbackHandler;
use App\Services\Telegram\Handlers\ContactHandler;
use App\Services\Telegram\Handlers\LocationHandler;
use App\Services\Telegram\Handlers\MessageHandler;
use App\Services\Telegram\Handlers\PhotoHandler;
use App\Services\Telegram\Handlers\TextHandler;
use App\Services\Telegram\Handlers\VideoHandler;
use Illuminate\Contracts\Container\Container;

class MessageDispatcher
{
    public function __construct(private readonly Container $container) {}

    /**
     * @param  array<string,mixed>  $update
     */
    public function dispatch(array $update): void
    {
        $handler = $this->resolveHandler($update);

        if ($handler !== null) {
            $handler->handle($update);
        }
    }

    /**
     * Resolve the appropriate handler in deterministic order.
     *
     * @param  array<string,mixed>  $update
     */
    private function resolveHandler(array $update): ?MessageHandler
    {
        // 1. callback_query
        if (isset($update['callback_query'])) {
            return $this->container->make(CallbackHandler::class);
        }

        $message = $update['message'] ?? null;

        if (! is_array($message)) {
            return null;
        }

        // 2. location
        if (isset($message['location'])) {
            return $this->container->make(LocationHandler::class);
        }

        // 3. contact
        if (isset($message['contact'])) {
            return $this->container->make(ContactHandler::class);
        }

        // 4. video
        if (isset($message['video'])) {
            return $this->container->make(VideoHandler::class);
        }

        // 5. photo
        if (isset($message['photo'])) {
            return $this->container->make(PhotoHandler::class);
        }

        // 6. text
        if (isset($message['text'])) {
            return $this->container->make(TextHandler::class);
        }

        return null;
    }
}
