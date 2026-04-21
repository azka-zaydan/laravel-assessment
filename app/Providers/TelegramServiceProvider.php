<?php

namespace App\Providers;

use App\Services\Telegram\TelegramBotService;
use Illuminate\Support\ServiceProvider;

class TelegramServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TelegramBotService::class, fn () => new TelegramBotService);
    }

    public function boot(): void {}
}
