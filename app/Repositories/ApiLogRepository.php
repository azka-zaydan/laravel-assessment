<?php

namespace App\Repositories;

use App\Models\ApiLog;
use Illuminate\Support\Carbon;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class ApiLogRepository
{
    /** @param array<string, mixed> $payload */
    public function create(array $payload): ApiLog
    {
        return ApiLog::create($payload);
    }

    /** @return QueryBuilder<ApiLog> */
    public function queryBuilder(): QueryBuilder
    {
        return QueryBuilder::for(ApiLog::class)
            ->allowedFilters(
                'user_id',
                'method',
                'response_status',
                'path',
                AllowedFilter::callback('from', fn ($q, $v) => $q->where('created_at', '>=', $v)),
                AllowedFilter::callback('to', fn ($q, $v) => $q->where('created_at', '<=', $v)),
            )
            ->allowedSorts(
                AllowedSort::field('created_at'),
                AllowedSort::field('duration_ms'),
                AllowedSort::field('response_status'),
            )
            ->defaultSort('-created_at');
    }

    public function pruneOlderThan(Carbon $cutoff): int
    {
        return ApiLog::where('created_at', '<', $cutoff)->delete();
    }
}
