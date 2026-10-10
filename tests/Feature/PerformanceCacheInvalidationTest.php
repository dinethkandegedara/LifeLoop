<?php

namespace Tests\Feature;

use App\Models\RecurringSchedule;
use App\Models\ScheduleOccurrence;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkSession;
use App\Services\Reporting\ReportCacheService;
use App\Services\Reporting\ScheduleReportingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PerformanceCacheInvalidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Task $task;
    protected ScheduleReportingService $reportingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['timezone' => 'UTC']);
        $this->task = Task::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Core Focus Task',
        ]);
        $this->reportingService = app(ScheduleReportingService::class);
    }

    public function test_cache_invalidates_after_occurrence_completion_and_reopen(): void
    {
        $occurrence = ScheduleOccurrence::factory()->create([
            'user_id' => $this->user->id,
            'task_id' => $this->task->id,
            'scheduled_date' => now()->toDateString(),
            'start_time' => '09:00:00',
            'duration_minutes' => 60,
            'status' => 'pending',
        ]);

        // 1. Initial report: 0 completed hours
        $initialReport = $this->reportingService->getOverviewReport($this->user, 'today');
        $this->assertEquals(0.0, $initialReport['metrics']['completed_planned_hours']);
        $this->assertEquals(0, $initialReport['metrics']['completed_occurrences_count']);

        // 2. Subsequent call returns identical cached result
        $cachedReport = $this->reportingService->getOverviewReport($this->user, 'today');
        $this->assertEquals($initialReport, $cachedReport);

        // 3. Mark occurrence completed via HTTP endpoint
        $response = $this->actingAs($this->user)->patchJson("/occurrences/{$occurrence->id}/complete");
        $response->assertOk();

        // 4. Report should immediately reflect 1 completed hour
        $updatedReport = $this->reportingService->getOverviewReport($this->user, 'today');
        $this->assertEquals(1.0, $updatedReport['metrics']['completed_planned_hours']);
        $this->assertEquals(1, $updatedReport['metrics']['completed_occurrences_count']);

        // 5. Reopen occurrence back to pending
        $reopenResponse = $this->actingAs($this->user)->patchJson("/occurrences/{$occurrence->id}/reopen");
        $reopenResponse->assertOk();

        // 6. Report should immediately reflect 0 completed hours again
        $reopenedReport = $this->reportingService->getOverviewReport($this->user, 'today');
        $this->assertEquals(0.0, $reopenedReport['metrics']['completed_planned_hours']);
        $this->assertEquals(0, $reopenedReport['metrics']['completed_occurrences_count']);
    }

    public function test_cache_invalidates_after_occurrence_skipped(): void
    {
        $occurrence = ScheduleOccurrence::factory()->create([
            'user_id' => $this->user->id,
            'task_id' => $this->task->id,
            'scheduled_date' => now()->toDateString(),
            'start_time' => '10:00:00',
            'duration_minutes' => 90,
            'status' => 'pending',
        ]);

        $initialReport = $this->reportingService->getOverviewReport($this->user, 'today');
        $this->assertEquals(0, $initialReport['metrics']['skipped_occurrences_count']);

        // Skip occurrence
        $response = $this->actingAs($this->user)->patchJson("/occurrences/{$occurrence->id}/skip");
        $response->assertOk();

        // Report must reflect skipped occurrence
        $afterSkipReport = $this->reportingService->getOverviewReport($this->user, 'today');
        $this->assertEquals(1, $afterSkipReport['metrics']['skipped_occurrences_count']);
        $this->assertEquals(1.5, $afterSkipReport['metrics']['skipped_hours']);
    }

    public function test_cache_invalidates_after_work_session_store_update_and_delete(): void
    {
        // 1. Initial report: 0 actual hours
        $initialReport = $this->reportingService->getOverviewReport($this->user, 'today');
        $this->assertEquals(0.0, $initialReport['metrics']['actual_hours']);

        // 2. Add work session (120 minutes = 2.0 hours)
        $storeResponse = $this->actingAs($this->user)->postJson('/work-sessions', [
            'task_id' => $this->task->id,
            'started_at' => now()->startOfDay()->addHours(10)->toDateTimeString(),
            'duration_minutes' => 120,
            'is_manual' => true,
        ]);
        $storeResponse->assertStatus(201);
        $sessionId = $storeResponse->json('work_session.id');

        // 3. Report must reflect 2.0 actual hours
        $afterStoreReport = $this->reportingService->getOverviewReport($this->user, 'today');
        $this->assertEquals(2.0, $afterStoreReport['metrics']['actual_hours']);

        // 4. Update work session to 180 minutes (3.0 hours)
        $updateResponse = $this->actingAs($this->user)->putJson("/work-sessions/{$sessionId}", [
            'duration_minutes' => 180,
        ]);
        $updateResponse->assertOk();

        // 5. Report must reflect 3.0 actual hours
        $afterUpdateReport = $this->reportingService->getOverviewReport($this->user, 'today');
        $this->assertEquals(3.0, $afterUpdateReport['metrics']['actual_hours']);

        // 6. Delete work session
        $deleteResponse = $this->actingAs($this->user)->deleteJson("/work-sessions/{$sessionId}");
        $deleteResponse->assertOk();

        // 7. Report must revert to 0.0 actual hours
        $afterDeleteReport = $this->reportingService->getOverviewReport($this->user, 'today');
        $this->assertEquals(0.0, $afterDeleteReport['metrics']['actual_hours']);
    }

    public function test_cache_invalidates_after_recurrence_rule_edits(): void
    {
        $targetDate = now()->addDays(2)->startOfDay();

        // Create schedule rule
        $response = $this->actingAs($this->user)->postJson("/tasks/{$this->task->id}/schedules", [
            'type' => 'daily',
            'start_date' => $targetDate->toDateString(),
            'start_time' => '09:00:00',
            'duration_minutes' => 60,
        ]);
        $response->assertStatus(201);
        $scheduleId = $response->json('schedule.id');

        $initialReport = $this->reportingService->getOverviewReport($this->user, 'today', anchorDate: $targetDate);
        $this->assertEquals(1.0, $initialReport['metrics']['scheduled_hours']);

        // Edit schedule rule duration to 120 minutes with effective_date
        $updateResponse = $this->actingAs($this->user)->putJson("/schedules/{$scheduleId}", [
            'type' => 'daily',
            'start_date' => $targetDate->toDateString(),
            'start_time' => '09:00:00',
            'duration_minutes' => 120,
            'effective_date' => $targetDate->toDateString(),
        ]);
        $updateResponse->assertOk();

        // Cache must invalidate and report must reflect updated 2.0 scheduled hours
        $updatedReport = $this->reportingService->getOverviewReport($this->user, 'today', anchorDate: $targetDate);
        $this->assertEquals(2.0, $updatedReport['metrics']['scheduled_hours']);
    }

    public function test_user_data_isolation_in_cache(): void
    {
        $otherUser = User::factory()->create(['timezone' => 'UTC']);
        $otherTask = Task::factory()->create(['user_id' => $otherUser->id]);

        ScheduleOccurrence::factory()->create([
            'user_id' => $this->user->id,
            'task_id' => $this->task->id,
            'scheduled_date' => now()->toDateString(),
            'start_time' => '09:00:00',
            'duration_minutes' => 60,
            'status' => 'completed',
        ]);

        ScheduleOccurrence::factory()->create([
            'user_id' => $otherUser->id,
            'task_id' => $otherTask->id,
            'scheduled_date' => now()->toDateString(),
            'start_time' => '11:00:00',
            'duration_minutes' => 120,
            'status' => 'completed',
        ]);

        $userReport = $this->reportingService->getOverviewReport($this->user, 'today');
        $otherReport = $this->reportingService->getOverviewReport($otherUser, 'today');

        // Verify strict isolation
        $this->assertEquals(1.0, $userReport['metrics']['completed_planned_hours']);
        $this->assertEquals(2.0, $otherReport['metrics']['completed_planned_hours']);

        // Mutations on user 1 should NOT bust or affect user 2's cache
        $versionUserBefore = ReportCacheService::getUserVersion($this->user->id);
        $versionOtherBefore = ReportCacheService::getUserVersion($otherUser->id);

        ReportCacheService::invalidateUser($this->user->id);

        $this->assertGreaterThan($versionUserBefore, ReportCacheService::getUserVersion($this->user->id));
        $this->assertEquals($versionOtherBefore, ReportCacheService::getUserVersion($otherUser->id));
    }
}
