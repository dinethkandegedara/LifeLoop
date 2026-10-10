<?php

namespace Database\Factories;

use App\Models\RecurringSchedule;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringSchedule>
 */
class RecurringScheduleFactory extends Factory
{
    protected $model = RecurringSchedule::class;

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
            'type' => 'weekly',
            'start_date' => now()->toDateString(),
            'end_date' => null,
            'start_time' => '09:00:00',
            'duration_minutes' => 60,
            'timezone' => 'UTC',
            'interval' => 1,
            'weekdays' => [1, 3, 5], // Mon, Wed, Fri
            'month_day' => null,
            'month_week' => null,
            'month_weekday' => null,
            'selected_dates' => null,
            'exclusions' => [],
            'is_active' => true,
            'version' => 1,
        ];
    }
}
