<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecurringScheduleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(['one_time', 'daily', 'weekly', 'monthly_date', 'monthly_day', 'custom_dates'])],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'start_time' => ['required'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'timezone' => ['nullable', 'string', 'timezone:all'],
            'interval' => ['nullable', 'integer', 'min:1', 'max:365'],
            'weekdays' => ['nullable', 'array'],
            'weekdays.*' => ['integer', 'between:1,7'],
            'month_day' => ['nullable', 'integer', 'between:1,31'],
            'month_week' => ['nullable', 'integer', Rule::in([1, 2, 3, 4, 5, -1])],
            'month_weekday' => ['nullable', 'integer', 'between:1,7'],
            'selected_dates' => ['nullable', 'array'],
            'selected_dates.*' => ['date'],
            'exclusions' => ['nullable', 'array'],
            'exclusions.*' => ['date'],
        ];
    }
}
