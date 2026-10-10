<?php

namespace Tests\Feature;

use App\Models\RecurringSchedule;
use App\Models\ScheduleOccurrence;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkSession;
use App\Services\Reporting\ScheduleReportingService;
use App\Services\Scheduling\ScheduleManager;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ScheduleReportingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ScheduleReportingService $reportingService;
    protected ScheduleManager $scheduleManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scheduleManager = app(ScheduleManager::class);
        $this->reportingService = app(ScheduleReportingService::class);
    }

    public function test_deterministic_metrics_calculation_and_definitions(): void
    {
        $user = User::factory()->create(['timezone' => 'UTC']);
        $task = Task::factory()->create(['user_id' => $user->id]);

        $baseDate = '2026-10-14';

        // 1. Completed occurrence: 120 mins (2.0 hrs)
        $completedOcc = ScheduleOccurrence::factory()->create([
            'user_id' => $user->id,
            'task_id' => $task->id,
            'scheduled_date' => $baseDate,
            'start_time' => '09:00:00',
            'duration_minutes' => 120,
            'status' => 'completed',
            'completed_at' => Carbon::parse("{$baseDate} 11:00:00"),
        ]);

        // 2. Pending occurrence: 60 mins (1.0 hr)
        $pendingOcc = ScheduleOccurrence::factory()->create([
            'user_id' => $user->id,
            'task_id' => $task->id,
            'scheduled_date' => $baseDate,
            'start_time' => '13:00:00',
            'duration_minutes' => 60,
            'status' => 'pending',
        ]);

        // 3. Skipped occurrence: 60 mins (1.0 hr)
        $skippedOcc = ScheduleOccurrence::factory()->create([
            'user_id' => $user->id,
            'task_id' => $task->id,
            'scheduled_date' => $baseDate,
            'start_time' => '15:00:00',
            'duration_minutes' => 60,
            'status' => 'skipped',
        ]);

        // 4. Work session linked to completed occurrence: 120 mins (2.0 hrs)
        WorkSession::factory()->create([
            'user_id' => $user->id,
            'task_id' => $task->id,
            'schedule_occurrence_id' => $completedOcc->id,
            'started_at' => "{$baseDate} 09:00:00",
            'duration_minutes' => 120,
        ]);

        // 5. Unscheduled extra work session: 60 mins (1.0 hr)
        WorkSession::factory()->create([
            'user_id' => $user->id,
            'task_id' => $task->id,
            'schedule_occurrence_id' => null,
            'started_at' => "{$baseDate} 17:00:00",
            'duration_minutes' => 60,
        ]);

        $start = Carbon::parse($baseDate)->startOfDay();
        $end = Carbon::parse($baseDate)->endOfDay();

        $metrics = $this->reportingService->getMetrics($user, $start, $end, $task->id);

        // Eligible scheduled hours = completed (2.0) + pending (1.0) = 3.0 hrs
        $this->assertEquals(3.0, $metrics['scheduled_hours']);
        $this->assertEquals(2.0, $metrics['completed_planned_hours']);
        $this->assertEquals(1.0, $metrics['remaining_scheduled_hours']);
        $this->assertEquals(1.0, $metrics['skipped_hours']);

        // Occurrences counts
        $this->assertEquals(2, $metrics['scheduled_occurrences_count']); // eligible
        $this->assertEquals(1, $metrics['completed_occurrences_count']);
        $this->assertEquals(1, $metrics['incomplete_occurrences_count']);
        $this->assertEquals(1, $metrics['skipped_occurrences_count']);
        $this->assertEquals(3, $metrics['total_occurrences_count']);

        // Completion rates
        $this->assertEquals(50.0, $metrics['occurrence_completion_rate']); // 1 / 2 * 100
        $this->assertEquals(66.7, $metrics['planned_hour_completion_rate']); // 2.0 / 3.0 * 100

        // Actual hours
        $this->assertEquals(3.0, $metrics['actual_hours']); // 2.0 + 1.0
        $this->assertEquals(1.0, $metrics['unscheduled_actual_hours']);
        $this->assertEquals(2.0, $metrics['scheduled_actual_hours']);
    }

    public function test_skipped_work_is_excluded_from_eligible_completion_rate(): void
    {
        $user = User::factory()->create(['timezone' => 'UTC']);
        $task = Task::factory()->create(['user_id' => $user->id]);

        $date = '2026-10-14';

        // 1 completed (1.0h)
        ScheduleOccurrence::factory()->create([
            'user_id' => $user->id,
            'task_id' => $task->id,
            'scheduled_date' => $date,
            'duration_minutes' => 60,
            'status' => 'completed',
        ]);

        // 1 skipped (1.0h)
        ScheduleOccurrence::factory()->create([
            'user_id' => $user->id,
            'task_id' => $task->id,
            'scheduled_date' => $date,
            'duration_minutes' => 60,
            'status' => 'skipped',
        ]);

        $start = Carbon::parse($date)->startOfDay();
        $end = Carbon::parse($date)->endOfDay();

        $metrics = $this->reportingService->getMetrics($user, $start, $end, $task->id);

        // Eligible count is 1, completed is 1 -> completion rate 100%
        $this->assertEquals(1.0, $metrics['scheduled_hours']);
        $this->assertEquals(1.0, $metrics['completed_planned_hours']);
        $this->assertEquals(1, $metrics['scheduled_occurrences_count']);
        $this->assertEquals(1, $metrics['completed_occurrences_count']);
        $this->assertEquals(1, $metrics['skipped_occurrences_count']);
        $this->assertEquals(100.0, $metrics['occurrence_completion_rate']);
    }

    public function test_overdue_versus_future_incomplete_occurrences(): void
    {
        $user = User::factory()->create(['timezone' => 'UTC']);
        $task = Task::factory()->create(['user_id' => $user->id]);

        $yesterday = now('UTC')->subDay()->toDateString();
        $tomorrow = now('UTC')->addDay()->toDateString();

        // 1. Past incomplete: Overdue
        ScheduleOccurrence::factory()->create([
            'user_id' => $user->id,
            'task_id' => $task->id,
            'scheduled_date' => $yesterday,
            'start_time' => '10:00:00',
            'duration_minutes' => 60,
            'status' => 'pending',
        ]);

        // 2. Future incomplete: Remaining scheduled work, NOT overdue
        ScheduleOccurrence::factory()->create([
            'user_id' => $user->id,
            'task_id' => $task->id,
            'scheduled_date' => $tomorrow,
            'start_time' => '10:00:00',
            'duration_minutes' => 60,
            'status' => 'pending',
        ]);

        $start = now('UTC')->subDays(2)->startOfDay();
        $end = now('UTC')->addDays(2)->endOfDay();

        $metrics = $this->reportingService->getMetrics($user, $start, $end, $task->id);

        $this->assertEquals(2, $metrics['incomplete_occurrences_count']);
        $this->assertEquals(2.0, $metrics['remaining_scheduled_hours']);
        $this->assertEquals(1, $metrics['overdue_incomplete_count']); // Only yesterday's
    }

    public function test_completion_status_corrections_update_report_metrics(): void
    {
        $user = User::factory()->create(['timezone' => 'UTC']);
        $task = Task::factory()->create(['user_id' => $user->id]);
        $date = '2026-10-14';

        $occ = ScheduleOccurrence::factory()->create([
            'user_id' => $user->id,
            'task_id' => $task->id,
            'scheduled_date' => $date,
            'duration_minutes' => 60,
            'status' => 'completed',
        ]);

        $start = Carbon::parse($date)->startOfDay();
        $end = Carbon::parse($date)->endOfDay();

        $initial = $this->reportingService->getMetrics($user, $start, $end, $task->id);
        $this->assertEquals(1.0, $initial['completed_planned_hours']);
        $this->assertEquals(100.0, $initial['occurrence_completion_rate']);

        // Correction: Reopen occurrence
        $occ->update(['status' => 'pending', 'completed_at' => null]);

        $corrected = $this->reportingService->getMetrics($user, $start, $end, $task->id);
        $this->assertEquals(0.0, $corrected['completed_planned_hours']);
        $this->assertEquals(1.0, $corrected['remaining_scheduled_hours']);
        $this->assertEquals(0.0, $corrected['occurrence_completion_rate']);
    }

    public function test_work_session_edits_and_actual_date_attribution(): void
    {
        $user = User::factory()->create(['timezone' => 'UTC']);
        $task = Task::factory()->create(['user_id' => $user->id]);

        // Scheduled on Monday 2026-10-12
        $occ = ScheduleOccurrence::factory()->create([
            'user_id' => $user->id,
            'task_id' => $task->id,
            'scheduled_date' => '2026-10-12',
            'duration_minutes' => 60,
            'status' => 'completed',
        ]);

        // Work session actually performed on Tuesday 2026-10-13 (attributed to Tuesday)
        $ws = WorkSession::factory()->create([
            'user_id' => $user->id,
            'task_id' => $task->id,
            'schedule_occurrence_id' => $occ->id,
            'started_at' => '2026-10-13 14:00:00',
            'duration_minutes' => 60,
        ]);

        // Report for Monday 2026-10-12: Scheduled hours = 1.0, Actual hours = 0.0
        $mondayStart = Carbon::parse('2026-10-12')->startOfDay();
        $mondayEnd = Carbon::parse('2026-10-12')->endOfDay();
        $mondayReport = $this->reportingService->getMetrics($user, $mondayStart, $mondayEnd, $task->id);
        $this->assertEquals(1.0, $mondayReport['scheduled_hours']);
        $this->assertEquals(0.0, $mondayReport['actual_hours']);

        // Report for Tuesday 2026-10-13: Scheduled hours = 0.0, Actual hours = 1.0
        $tuesdayStart = Carbon::parse('2026-10-13')->startOfDay();
        $tuesdayEnd = Carbon::parse('2026-10-13')->endOfDay();
        $tuesdayReport = $this->reportingService->getMetrics($user, $tuesdayStart, $tuesdayEnd, $task->id);
        $this->assertEquals(0.0, $tuesdayReport['scheduled_hours']);
        $this->assertEquals(1.0, $tuesdayReport['actual_hours']);

        // Work session edit: increase duration to 90 mins
        $ws->update(['duration_minutes' => 90]);
        $updatedTuesday = $this->reportingService->getMetrics($user, $tuesdayStart, $tuesdayEnd, $task->id);
        $this->assertEquals(1.5, $updatedTuesday['actual_hours']);
    }

    public function test_empty_periods_handled_without_division_by_zero(): void
    {
        $user = User::factory()->create(['timezone' => 'UTC']);
        $task = Task::factory()->create(['user_id' => $user->id]);

        $emptyStart = Carbon::parse('2025-01-01')->startOfDay();
        $emptyEnd = Carbon::parse('2025-01-07')->endOfDay();

        $metrics = $this->reportingService->getMetrics($user, $emptyStart, $emptyEnd, $task->id);

        $this->assertEquals(0.0, $metrics['scheduled_hours']);
        $this->assertEquals(0.0, $metrics['completed_planned_hours']);
        $this->assertEquals(0.0, $metrics['remaining_scheduled_hours']);
        $this->assertEquals(0.0, $metrics['actual_hours']);
        $this->assertEquals(0, $metrics['scheduled_occurrences_count']);
        $this->assertEquals(0.0, $metrics['occurrence_completion_rate']);
        $this->assertEquals(0.0, $metrics['planned_hour_completion_rate']);
    }

    public function test_timezone_boundaries_respected(): void
    {
        // Tokyo is UTC+9
        $user = User::factory()->create(['timezone' => 'Asia/Tokyo']);

        $boundaries = $this->reportingService->getPeriodBoundaries($user, 'today');

        $this->assertEquals('Asia/Tokyo', $boundaries['start']->getTimezone()->getName());
        $this->assertEquals('00:00:00', $boundaries['start']->format('H:i:s'));
        $this->assertEquals('23:59:59', $boundaries['end']->format('H:i:s'));
    }

    public function test_reports_overview_and_task_report_http_endpoints(): void
    {
        $user = User::factory()->create(['timezone' => 'UTC']);
        $task = Task::factory()->create(['user_id' => $user->id]);

        // 1. Overview report page
        $overviewResponse = $this->actingAs($user)->get('/reports?period=week');
        $overviewResponse->assertStatus(200);
        $overviewResponse->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Index')
            ->where('period', 'week')
            ->has('metrics')
            ->has('task_summaries')
            ->has('daily_trends')
        );

        // 2. Task report page
        $taskReportResponse = $this->actingAs($user)->get("/tasks/{$task->id}/report?period=month");
        $taskReportResponse->assertStatus(200);
        $taskReportResponse->assertInertia(fn (Assert $page) => $page
            ->component('Reports/TaskReport')
            ->where('task.id', $task->id)
            ->where('period', 'month')
            ->has('metrics')
            ->has('daily_trends')
            ->has('weekly_trends')
            ->has('monthly_trends')
            ->has('occurrences_history')
            ->has('work_sessions_history')
        );

        // 3. User isolation: Another user cannot access task report
        $otherUser = User::factory()->create();
        $unauthorizedResponse = $this->actingAs($otherUser)->get("/tasks/{$task->id}/report");
        $unauthorizedResponse->assertStatus(403);
    }
}
