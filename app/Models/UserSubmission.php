<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $chat_id
 * @property string $type
 * @property string $file_id
 * @property int $message_id
 * @property array<string,mixed> $raw_update
 * @property Carbon|null $processed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class UserSubmission extends Model
{
    protected $fillable = [
        'user_id',
        'chat_id',
        'type',
        'file_id',
        'message_id',
        'raw_update',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'raw_update' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
