<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $phone_number
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class UserFavorite extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'phone_number',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
