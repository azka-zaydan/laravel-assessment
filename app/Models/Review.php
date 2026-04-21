<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
