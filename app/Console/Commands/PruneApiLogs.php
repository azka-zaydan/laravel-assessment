<?php

namespace App\Console\Commands;

use App\Repositories\ApiLogRepository;
use Illuminate\Console\Command;

class PruneApiLogs extends Command
{
    protected $signature = 'logs:prune {--days= : Number of days to retain (overrides config)}';

    protected $description = 'Delete API log entries older than the retention window';

    public function handle(ApiLogRepository $repo): int
    {
        $days = $this->option('days') !== null
            ? (int) $this->option('days')
            : (int) config('logging.api_retention_days', 30);

        $cutoff = now()->subDays($days);
        $count = $repo->pruneOlderThan($cutoff);

        $this->info("Pruned {$count} API log entries older than {$days} days.");

        return self::SUCCESS;
    }
}
