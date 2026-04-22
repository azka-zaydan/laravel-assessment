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
                // Integer / smallint columns: exact match. The default spatie
                // "simple filter" compiles to `LOWER(col) LIKE LOWER('%v%')`
                // which blows up on Postgres non-text columns with
                // `function lower(smallint) does not exist` (and same for
                // bigint on user_id). Exact comparison is the right semantics
                // here too — nobody wants partial-match on a status code.
                AllowedFilter::exact('user_id'),
                AllowedFilter::exact('response_status'),
                // Text columns: partial case-insensitive match is fine.
                'method',
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
