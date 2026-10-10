<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkSessionRequest extends FormRequest
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
            'task_id' => ['sometimes', 'required', 'integer', 'exists:tasks,id'],
            'schedule_occurrence_id' => ['nullable', 'integer', 'exists:schedule_occurrences,id'],
            'started_at' => ['sometimes', 'required', 'date'],
            'ended_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'duration_minutes' => ['sometimes', 'required', 'integer', 'min:1', 'max:1440'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
