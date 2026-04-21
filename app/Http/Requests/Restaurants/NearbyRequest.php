<?php

namespace App\Http\Requests\Restaurants;

use Illuminate\Foundation\Http\FormRequest;

class NearbyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string,array<int,mixed>|string>
     */
    public function rules(): array
    {
        return [
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lon' => ['required', 'numeric', 'between:-180,180'],
            'count' => ['nullable', 'integer', 'min:1', 'max:20'],
        ];
    }
}
