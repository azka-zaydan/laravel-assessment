<?php

use App\Providers\AppServiceProvider;
use App\Providers\RestaurantServiceProvider;
use App\Providers\TelegramServiceProvider;

return [
    AppServiceProvider::class,
    RestaurantServiceProvider::class,
    TelegramServiceProvider::class,
];
