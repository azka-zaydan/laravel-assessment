<?php

namespace App\Observers;

use App\Models\ApiLog;
use Illuminate\Support\Str;

class ApiLogObserver
{
    public function creating(ApiLog $apiLog): void
    {
        if (empty($apiLog->request_id)) {
            $apiLog->request_id = (string) Str::ulid();
        }

        if (empty($apiLog->created_at)) {
            $apiLog->created_at = now();
        }
    }
}
