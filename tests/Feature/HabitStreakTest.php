<?php

namespace Tests\Feature;

use App\Models\ScheduleOccurrence;
use App\Models\Task;
use App\Models\User;
use App\Services\Habits\StreakService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HabitStreakTest extends TestCase
{
    use RefreshDatabase;

    protected StreakService $streakService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->streakService = app(StreakService::class);
    }

    public function test_new_user_has_zero_streak(): void
    {
        $user = User::factory()->create(['timezone' => 'UTC']);

        $data = $this->streakService->getStreakData($user);

        $this->assertEquals(0, $data['current_streak']);
        $this->assertEquals(0, $data['best_streak']);
        $this->assertEquals('Ready to Start', $data['badge_name']);
        $this->assertFalse($data['today_qualified']);
    }

    public function test_consecutive_days_with_eighty_percent_completion_form_a_streak(): void
    {
        $user = User::factory()->create(['timezone' => 'UTC']);
        $task = Task::factory()->create(['user_id' => $user->id]);

        $today = now('UTC')->startOfDay();

        // 3 consecutive days in the past (yesterday, 2 days ago, 3 days ago)
        for ($i = 1; $i <= 3; $i++) {
            $date = $today->copy()->subDays($i)->toDateString();

            // 5 occurrences scheduled: 4 completed, 1 pending (80% completion)
            for ($j = 0; $j < 4; $j++) {
                ScheduleOccurrence::factory()->create([
                    'user_id' => $user->id,
                    'task_id' => $task->id,
                    'scheduled_date' => $date,
                    'start_time' => sprintf('%02d:00:00', 9 + $j),
                    'duration_minutes' => 60,
                    'status' => 'completed',
                ]);
            }

            ScheduleOccurrence::factory()->create([
                'user_id' => $user->id,
                'task_id' => $task->id,
                'scheduled_date' => $date,
                'start_time' => '14:00:00',
                'duration_minutes' => 60,
                'status' => 'pending',
            ]);
        }

        $data = $this->streakService->getStreakData($user);

        // Even though today is not yet completed, yesterday backwards gives 3 days streak!
        $this->assertEquals(3, $data['current_streak']);
        $this->assertEquals('Bronze Momentum', $data['badge_name']);
    }

    public function test_day_below_eighty_percent_breaks_the_streak(): void
    {
        $user = User::factory()->create(['timezone' => 'UTC']);
        $task = Task::factory()->create(['user_id' => $user->id]);

        $today = now('UTC')->startOfDay();

        // Yesterday: 100% completion (1/1)
        ScheduleOccurrence::factory()->create([
            'user_id' => $user->id,
            'task_id' => $task->id,
            'scheduled_date' => $today->copy()->subDay()->toDateString(),
            'start_time' => '09:00:00',
            'duration_minutes' => 60,
            'status' => 'completed',
        ]);

        // 2 days ago: 50% completion (1/2) -> breaks streak
        ScheduleOccurrence::factory()->create([
            'user_id' => $user->id,
            'task_id' => $task->id,
            'scheduled_date' => $today->copy()->subDays(2)->toDateString(),
            'start_time' => '09:00:00',
            'duration_minutes' => 60,
            'status' => 'completed',
        ]);
        ScheduleOccurrence::factory()->create([
            'user_id' => $user->id,
            'task_id' => $task->id,
            'scheduled_date' => $today->copy()->subDays(2)->toDateString(),
            'start_time' => '11:00:00',
            'duration_minutes' => 60,
            'status' => 'pending',
        ]);

        // 3 days ago: 100% completion (1/1)
        ScheduleOccurrence::factory()->create([
            'user_id' => $user->id,
            'task_id' => $task->id,
            'scheduled_date' => $today->copy()->subDays(3)->toDateString(),
            'start_time' => '09:00:00',
            'duration_minutes' => 60,
            'status' => 'completed',
        ]);

        $data = $this->streakService->getStreakData($user);

        // Only yesterday qualifies consecutively, streak is 1
        $this->assertEquals(1, $data['current_streak']);
    }

    public function test_home_page_renders_with_streak_data(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'timezone' => 'UTC',
        ]);

        $response = $this->actingAs($user)->get('/');
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Today')
            ->has('streak')
            ->where('streak.current_streak', 0)
            ->where('streak.badge_name', 'Ready to Start')
        );
    }
}
