<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array<string,mixed> $resource
 */
class ReviewResource extends JsonResource
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
            'rating' => $r['rating'],
            'review_text' => $r['review_text'],
            'user' => $r['user'],
            'created_at' => $r['created_at'],
        ];
    }
}
