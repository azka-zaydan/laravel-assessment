<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $zomato_id
 * @property string $name
 * @property ?string $address
 * @property ?float $rating
 * @property ?array<int,string> $cuisines
 * @property ?float $latitude
 * @property ?float $longitude
 * @property ?string $phone
 * @property ?string $thumb_url
 * @property ?string $image_url
 * @property ?array<string,mixed> $hours
 * @property ?array<string,mixed> $raw
 */
class Restaurant extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'zomato_id',
        'name',
        'address',
        'rating',
        'cuisines',
        'latitude',
        'longitude',
        'phone',
        'thumb_url',
        'image_url',
        'hours',
        'raw',
    ];

    /**
     * @return array<string,string>
     */
    protected function casts(): array
    {
        return [
            'cuisines' => 'array',
            'hours' => 'array',
            'raw' => 'array',
            'rating' => 'float',
            'latitude' => 'float',
            'longitude' => 'float',
            'zomato_id' => 'integer',
        ];
    }

    /**
     * @return HasMany<Review, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * @return HasMany<MenuItem, $this>
     */
    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }
}
