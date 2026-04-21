<?php

namespace App\Services\Telegram\Commands;

interface Command
{
    /**
     * @param  array<string,mixed>  $message
     * @param  string[]  $args
     */
    public function handle(array $message, array $args): void;
}
