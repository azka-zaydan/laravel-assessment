<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $chat_id
 * @property string|null $username
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $language_code
 * @property Carbon|null $last_message_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TelegramUser extends Model
{
    protected $fillable = [
        'user_id',
        'chat_id',
        'username',
        'first_name',
        'last_name',
        'language_code',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
