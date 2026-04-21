<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array<string,mixed> $resource
 */
class RestaurantResource extends JsonResource
{
    /**
     * @return array<string,mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string,mixed> $r */
        $r = $this->resource;

        return [
            'id' => $r['id'],
            'name' => $r['name'],
            'address' => $r['address'],
            'rating' => $r['rating'],
            'cuisines' => $r['cuisines'],
            'location' => $r['location'],
            'thumb_url' => $r['thumb_url'],
            'image_url' => $r['image_url'] ?? null,
            'phone' => $r['phone'] ?? null,
            'hours' => $r['hours'] ?? null,
            'menu_url' => $r['menu_url'] ?? null,
        ];
    }
}
