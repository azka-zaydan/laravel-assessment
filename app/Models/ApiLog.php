<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $request_id
 * @property int|null $user_id
 * @property string $method
 * @property string $path
 * @property string|null $route_name
 * @property string|null $ip
 * @property string|null $user_agent
 * @property array<string, mixed> $headers
 * @property array<string, mixed> $body
 * @property int $response_status
 * @property int $response_size_bytes
 * @property int $duration_ms
 * @property Carbon $created_at
 * @property-read User|null $user
 */
class ApiLog extends Model
{
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'request_id',
        'user_id',
        'method',
        'path',
        'route_name',
        'ip',
        'user_agent',
        'headers',
        'body',
        'response_status',
        'response_size_bytes',
        'duration_ms',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'body' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
