<?php

namespace Database\Factories;

use App\Models\RecurringSchedule;
use App\Models\ScheduleOccurrence;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduleOccurrence>
 */
class ScheduleOccurrenceFactory extends Factory
{
    protected $model = ScheduleOccurrence::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'task_id' => Task::factory(),
            'recurring_schedule_id' => null,
            'scheduled_date' => now()->toDateString(),
            'start_time' => '09:00:00',
            'duration_minutes' => 60,
            'end_time' => '10:00:00',
            'timezone' => 'UTC',
            'utc_start_at' => now(),
            'utc_end_at' => now()->addHour(),
            'status' => 'pending',
            'is_exception' => false,
            'original_scheduled_date' => null,
            'original_start_time' => null,
            'original_duration_minutes' => null,
            'notes' => null,
            'superseded_at' => null,
            'completed_at' => null,
        ];
    }
}
