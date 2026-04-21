<?php

namespace App\Exceptions\Restaurants;

use RuntimeException;

class RateLimitExceededException extends RuntimeException
{
    public function __construct(string $message = 'Zomato daily rate limit exceeded.', int $code = 429)
    {
        parent::__construct($message, $code);
    }
}
