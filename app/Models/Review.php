<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $zomato_id
 * @property int $restaurant_id
 * @property string $user_name
 * @property ?string $user_thumb_url
 * @property float $rating
 * @property string $review_text
 * @property ?Carbon $posted_at
 * @property ?array<string,mixed> $raw
 */
class Review extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'zomato_id',
        'restaurant_id',
        'user_name',
        'user_thumb_url',
        'rating',
        'review_text',
        'posted_at',
        'raw',
    ];

    /**
     * @return array<string,string>
     */
    protected function casts(): array
    {
        return [
            'raw' => 'array',
            'rating' => 'float',
            'zomato_id' => 'integer',
            'posted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Restaurant, $this>
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
