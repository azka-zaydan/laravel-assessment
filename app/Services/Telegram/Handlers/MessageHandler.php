<?php

namespace App\Services\Telegram\Handlers;

interface MessageHandler
{
    /**
     * @param  array<string,mixed>  $update
     */
    public function handle(array $update): void;
}
