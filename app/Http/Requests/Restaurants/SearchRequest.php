<?php

namespace App\Http\Requests\Restaurants;

use Illuminate\Foundation\Http\FormRequest;

class SearchRequest extends FormRequest
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
            'q' => ['nullable', 'string', 'max:255'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lon' => ['nullable', 'numeric', 'between:-180,180'],
            'cuisine' => ['nullable', 'string', 'max:255'],
            'count' => ['nullable', 'integer', 'min:1', 'max:20'],
            'start' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function criteria(): array
    {
        return array_filter([
            'q' => $this->input('q'),
            'lat' => $this->input('lat') !== null ? (float) $this->input('lat') : null,
            'lon' => $this->input('lon') !== null ? (float) $this->input('lon') : null,
            'cuisine' => $this->input('cuisine'),
            'count' => $this->input('count') !== null ? (int) $this->input('count') : 20,
            'start' => $this->input('start') !== null ? (int) $this->input('start') : 0,
        ], fn ($v) => $v !== null);
    }
}
