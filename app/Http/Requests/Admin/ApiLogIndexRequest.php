<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates filter inputs on GET /api/admin/api-logs BEFORE they hit the
 * query builder. Without this, a `filter[response_status]=abc` would flow
 * through spatie's AllowedFilter::exact as a string and Postgres would
 * reject it with SQLSTATE[22P02] invalid input syntax for type smallint
 * — surfacing as a 500 Server Error. Here it's a clean 422 with a
 * per-field error message.
 */
class ApiLogIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gate is the `can:admin` middleware on the route
    }

    /**
     * @return array<string,array<int,mixed>|string>
     */
    public function rules(): array
    {
        return [
            'filter.response_status' => ['nullable', 'integer', 'between:100,599'],
            'filter.user_id' => ['nullable', 'integer', 'min:1'],
            'filter.method' => ['nullable', 'string', 'in:GET,POST,PUT,PATCH,DELETE,OPTIONS,HEAD'],
            'filter.path' => ['nullable', 'string', 'max:255'],
            'filter.from' => ['nullable', 'date'],
            'filter.to' => ['nullable', 'date'],
            // per_page deliberately NOT rule-capped here — the controller
            // clamps oversize values to 200 (existing "clamp" contract). A
            // strict validator would 422 the legacy behaviour callers rely on.
            'per_page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
