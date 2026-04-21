<?php

namespace App\Jobs;

use App\Repositories\ApiLogRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class LogApiRequestJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /** @param array<string, mixed> $payload */
    public function __construct(private readonly array $payload) {}

    public function handle(ApiLogRepository $repo): void
    {
        $repo->create($this->payload);
    }
}
