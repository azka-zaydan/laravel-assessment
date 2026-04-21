<?php

namespace App\Http\Resources;

use App\Models\ApiLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ApiLog
 */
class ApiLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ApiLog $log */
        $log = $this->resource;

        return [
            'id' => $log->id,
            'request_id' => $log->request_id,
            'user_id' => $log->user_id,
            'user' => $this->when(
                $log->relationLoaded('user') && $log->user !== null,
                fn () => [
                    'id' => $log->user?->id,
                    'name' => $log->user?->name,
                    'email' => $log->user?->email,
                ]
            ),
            'method' => $log->method,
            'path' => $log->path,
            'route_name' => $log->route_name,
            'ip' => $log->ip,
            'user_agent' => $log->user_agent,
            'headers' => $log->headers,
            'body' => $log->body,
            'response_status' => $log->response_status,
            'response_size_bytes' => $log->response_size_bytes,
            'duration_ms' => $log->duration_ms,
            'created_at' => $log->created_at->toIso8601String(),
        ];
    }
}
