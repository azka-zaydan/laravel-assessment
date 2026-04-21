<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array<string,mixed> $resource
 */
class MenuItemResource extends JsonResource
{
    /**
     * @return array<string,mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string,mixed> $r */
        $r = $this->resource;

        return [
            'name' => $r['name'],
            'price' => $r['price'],
            'description' => $r['description'] ?? null,
        ];
    }
}
