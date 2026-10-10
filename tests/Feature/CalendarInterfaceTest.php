<?php

namespace Tests\Feature;

use App\Models\ScheduleOccurrence;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkSession;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CalendarInterfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_today_view_renders_with_expected_props(): void
    {
        $user = User::factory()->create(['timezone' => 'America/New_York']);
        $task = Task::factory()->create(['user_id' => $user->id, 'title' => 'Client Review']);

        $today = now('America/New_York')->toDateString();
        ScheduleOccurrence::factory()->create([
            'user_id' => $user->id,
            'task_id' => $task->id,
            'scheduled_date' => $today,
            'start_time' => '10:00:00',
            'duration_minutes' => 90,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user)->get('/?view=today');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Today')
            ->where('currentView', 'today')
            ->where('userTimezone', 'America/New_York')
            ->where('selectedDate', $today)
            ->has('occurrences', 1)
            ->has('tasks')
            ->has('todayWorkSessions')
            ->has('overdueOccurrences')
        );
    }

    public function test_calendar_week_view_renders_with_weekly_occurrences_and_boundaries(): void
    {
        $user = User::factory()->create(['timezone' => 'UTC']);
        $task = Task::factory()->create(['user_id' => $user->id]);

        // Anchor date: Wednesday 2026-10-14
        $anchor = Carbon::parse('2026-10-14', 'UTC');
        $weekMonday = '2026-10-12';
        $weekSunday = '2026-10-18';

        // Occurrence on Friday 2026-10-16
        ScheduleOccurrence::factory()->create([
            'user_id' => $user->id,
            'task_id' => $task->id,
            'scheduled_date' => '2026-10-16',
            'start_time' => '14:00:00',
            'duration_minutes' => 60,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user)->get('/week?date=2026-10-14');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Today')
            ->where('currentView', 'week')
            ->where('rangeStart', $weekMonday)
            ->where('rangeEnd', $weekSunday)
            ->has('occurrences', 1)
        );
    }

    public function test_calendar_month_view_renders_with_full_grid_range(): void
    {
        $user = User::factory()->create(['timezone' => 'UTC']);
        $task = Task::factory()->create(['user_id' => $user->id]);

        // October 2026 starts on Thursday Oct 1 (week start Monday Sep 28)
        // October 2026 ends on Saturday Oct 31 (week end Sunday Nov 1)
        $response = $this->actingAs($user)->get('/month?date=2026-10-15');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Today')
            ->where('currentView', 'month')
            ->where('rangeStart', '2026-09-28')
            ->where('rangeEnd', '2026-11-01')
        );
    }

    public function test_navigation_context_preserves_selected_date(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/?view=today&date=2026-12-25');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->where('selectedDate', '2026-12-25')
        );
    }

    public function test_overdue_tasks_detected_on_today_view(): void
    {
        $user = User::factory()->create(['timezone' => 'UTC']);
        $task = Task::factory()->create(['user_id' => $user->id]);

        $yesterday = now('UTC')->subDay()->toDateString();
        $overdue = ScheduleOccurrence::factory()->create([
            'user_id' => $user->id,
            'task_id' => $task->id,
            'scheduled_date' => $yesterday,
            'start_time' => '09:00:00',
            'duration_minutes' => 60,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user)->get('/?view=today');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->has('overdueOccurrences', 1)
            ->where('overdueOccurrences.0.id', $overdue->id)
        );
    }

    public function test_occurrence_quick_completion_and_reopening_via_json(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id]);
        $occ = ScheduleOccurrence::factory()->create([
            'user_id' => $user->id,
            'task_id' => $task->id,
            'status' => 'pending',
        ]);

        // Complete
        $completeResponse = $this->actingAs($user)
            ->patchJson("/occurrences/{$occ->id}/complete");

        $completeResponse->assertStatus(200);
        $completeResponse->assertJson(['message' => 'Occurrence marked as completed.']);
        $this->assertEquals('completed', $occ->fresh()->status);

        // Reopen
        $reopenResponse = $this->actingAs($user)
            ->patchJson("/occurrences/{$occ->id}/reopen");

        $reopenResponse->assertStatus(200);
        $reopenResponse->assertJson(['message' => 'Occurrence reopened successfully.']);
        $this->assertEquals('pending', $occ->fresh()->status);
    }

    public function test_quick_work_session_logging_via_json(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id]);
        $occ = ScheduleOccurrence::factory()->create([
            'user_id' => $user->id,
            'task_id' => $task->id,
            'scheduled_date' => '2026-10-14',
        ]);

        $response = $this->actingAs($user)->postJson('/work-sessions', [
            'task_id' => $task->id,
            'schedule_occurrence_id' => $occ->id,
            'started_at' => '2026-10-14 10:00:00',
            'duration_minutes' => 120,
            'notes' => 'Finished initial module',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('work_sessions', [
            'user_id' => $user->id,
            'task_id' => $task->id,
            'schedule_occurrence_id' => $occ->id,
            'duration_minutes' => 120,
        ]);
    }

    public function test_user_can_update_their_own_work_session(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id]);
        $ws = WorkSession::factory()->create([
            'user_id' => $user->id,
            'task_id' => $task->id,
            'started_at' => '2026-10-10 10:00:00',
            'duration_minutes' => 60,
            'notes' => 'Original notes',
        ]);

        $response = $this->actingAs($user)->putJson("/work-sessions/{$ws->id}", [
            'duration_minutes' => 90,
            'notes' => 'Updated notes after extension',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Work session updated successfully.']);

        $this->assertDatabaseHas('work_sessions', [
            'id' => $ws->id,
            'duration_minutes' => 90,
            'notes' => 'Updated notes after extension',
        ]);
    }

    public function test_user_cannot_update_another_users_work_session(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $userB->id]);
        $ws = WorkSession::factory()->create([
            'user_id' => $userB->id,
            'task_id' => $task->id,
        ]);

        $response = $this->actingAs($userA)->putJson("/work-sessions/{$ws->id}", [
            'duration_minutes' => 120,
        ]);

        $response->assertStatus(403);
    }

    public function test_user_can_delete_their_own_work_session(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id]);
        $ws = WorkSession::factory()->create([
            'user_id' => $user->id,
            'task_id' => $task->id,
            'duration_minutes' => 45,
        ]);

        $response = $this->actingAs($user)->deleteJson("/work-sessions/{$ws->id}");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Work session deleted successfully.']);
        $this->assertDatabaseMissing('work_sessions', ['id' => $ws->id]);
    }

    public function test_user_cannot_delete_another_users_work_session(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $userB->id]);
        $ws = WorkSession::factory()->create([
            'user_id' => $userB->id,
            'task_id' => $task->id,
        ]);

        $response = $this->actingAs($userA)->deleteJson("/work-sessions/{$ws->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('work_sessions', ['id' => $ws->id]);
    }
}
