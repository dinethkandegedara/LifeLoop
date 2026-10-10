<?php

namespace Tests\Feature;

use App\Models\RecurringSchedule;
use App\Models\ScheduleOccurrence;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkSession;
use App\Services\Scheduling\RecurrenceRuleEvaluator;
use App\Services\Scheduling\ScheduleManager;
use App\Services\Scheduling\ScheduleOccurrenceGenerator;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchedulingEngineTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Task $task;
    protected ScheduleManager $manager;
    protected ScheduleOccurrenceGenerator $generator;
    protected RecurrenceRuleEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['timezone' => 'America/New_York']);
        $this->task = Task::factory()->create(['user_id' => $this->user->id]);

        $this->evaluator = new RecurrenceRuleEvaluator();
        $this->generator = new ScheduleOccurrenceGenerator($this->evaluator);
        $this->manager = new ScheduleManager($this->generator);
    }

    public function test_weekly_and_biweekly_recurrence_rules(): void
    {
        // 1. Weekly on Monday (1), Wednesday (3), Friday (5) starting on Monday 2026-10-05
        $weeklySchedule = $this->manager->createSchedule($this->task, [
            'type' => 'weekly',
            'start_date' => '2026-10-05',
            'start_time' => '09:00:00',
            'duration_minutes' => 60,
            'interval' => 1,
            'weekdays' => [1, 3, 5],
            'timezone' => 'UTC',
        ], Carbon::parse('2026-10-18', 'UTC'));

        $occurrences = $weeklySchedule->occurrences()->where('status', 'pending')->orderBy('scheduled_date')->get();

        // 2 weeks of Mon/Wed/Fri = 6 occurrences
        $this->assertCount(6, $occurrences);
        $dates = $occurrences->pluck('scheduled_date')->map(fn ($d) => Carbon::parse($d)->toDateString())->toArray();
        $this->assertEquals([
            '2026-10-05', // Mon
            '2026-10-07', // Wed
            '2026-10-09', // Fri
            '2026-10-12', // Mon
            '2026-10-14', // Wed
            '2026-10-16', // Fri
        ], $dates);

        // 2. Biweekly (every 2 weeks) on Tuesday starting on 2026-10-06
        $biweeklySchedule = $this->manager->createSchedule($this->task, [
            'type' => 'weekly',
            'start_date' => '2026-10-06',
            'start_time' => '10:00:00',
            'duration_minutes' => 90,
            'interval' => 2,
            'weekdays' => [2], // Tuesday
            'timezone' => 'UTC',
        ], Carbon::parse('2026-11-18', 'UTC'));

        $biweeklyOccurrences = $biweeklySchedule->occurrences()->where('status', 'pending')->orderBy('scheduled_date')->get();
        $biweeklyDates = $biweeklyOccurrences->pluck('scheduled_date')->map(fn ($d) => Carbon::parse($d)->toDateString())->toArray();

        $this->assertEquals([
            '2026-10-06',
            '2026-10-20',
            '2026-11-03',
            '2026-11-17',
        ], $biweeklyDates);
    }

    public function test_monthly_date_and_clamping(): void
    {
        // Monthly on the 15th every 1 month
        $schedule15 = $this->manager->createSchedule($this->task, [
            'type' => 'monthly_date',
            'start_date' => '2026-01-15',
            'start_time' => '14:00:00',
            'duration_minutes' => 60,
            'interval' => 1,
            'month_day' => 15,
            'timezone' => 'UTC',
        ], Carbon::parse('2026-04-30', 'UTC'));

        $dates15 = $schedule15->occurrences()->orderBy('scheduled_date')->pluck('scheduled_date')->map(fn ($d) => Carbon::parse($d)->toDateString())->toArray();
        $this->assertEquals(['2026-01-15', '2026-02-15', '2026-03-15', '2026-04-15'], $dates15);

        // Monthly on the 31st: February has 28 days (non-leap 2026), April has 30 days
        $schedule31 = $this->manager->createSchedule($this->task, [
            'type' => 'monthly_date',
            'start_date' => '2026-01-31',
            'start_time' => '14:00:00',
            'duration_minutes' => 60,
            'interval' => 1,
            'month_day' => 31,
            'timezone' => 'UTC',
        ], Carbon::parse('2026-05-31', 'UTC'));

        $dates31 = $schedule31->occurrences()->orderBy('scheduled_date')->pluck('scheduled_date')->map(fn ($d) => Carbon::parse($d)->toDateString())->toArray();
        $this->assertEquals([
            '2026-01-31',
            '2026-02-28', // Clamped to Feb 28
            '2026-03-31',
            '2026-04-30', // Clamped to Apr 30
            '2026-05-31',
        ], $dates31);
    }

    public function test_monthly_weekday_position_rules(): void
    {
        // 1. Second Tuesday of the month (month_week = 2, month_weekday = 2)
        $secondTuesday = $this->manager->createSchedule($this->task, [
            'type' => 'monthly_day',
            'start_date' => '2026-10-01',
            'start_time' => '11:00:00',
            'duration_minutes' => 45,
            'interval' => 1,
            'month_week' => 2,
            'month_weekday' => 2, // Tuesday
            'timezone' => 'UTC',
        ], Carbon::parse('2026-12-31', 'UTC'));

        $dates2Tue = $secondTuesday->occurrences()->orderBy('scheduled_date')->pluck('scheduled_date')->map(fn ($d) => Carbon::parse($d)->toDateString())->toArray();
        $this->assertEquals([
            '2026-10-13', // 2nd Tuesday in Oct 2026
            '2026-11-10', // 2nd Tuesday in Nov 2026
            '2026-12-08', // 2nd Tuesday in Dec 2026
        ], $dates2Tue);

        // 2. Last Friday of the month (month_week = -1, month_weekday = 5)
        $lastFriday = $this->manager->createSchedule($this->task, [
            'type' => 'monthly_day',
            'start_date' => '2026-10-01',
            'start_time' => '16:00:00',
            'duration_minutes' => 60,
            'interval' => 1,
            'month_week' => -1,
            'month_weekday' => 5, // Friday
            'timezone' => 'UTC',
        ], Carbon::parse('2026-12-31', 'UTC'));

        $datesLastFri = $lastFriday->occurrences()->orderBy('scheduled_date')->pluck('scheduled_date')->map(fn ($d) => Carbon::parse($d)->toDateString())->toArray();
        $this->assertEquals([
            '2026-10-30', // Last Friday in Oct 2026
            '2026-11-27', // Last Friday in Nov 2026
            '2026-12-25', // Last Friday in Dec 2026
        ], $datesLastFri);
    }

    public function test_end_dates_and_exclusions(): void
    {
        // Daily rule with end date and exclusions
        $schedule = $this->manager->createSchedule($this->task, [
            'type' => 'daily',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-08',
            'start_time' => '08:00:00',
            'duration_minutes' => 30,
            'interval' => 1,
            'exclusions' => ['2026-10-03', '2026-10-05'],
            'timezone' => 'UTC',
        ], Carbon::parse('2026-10-31', 'UTC')); // Horizon is beyond end_date

        $dates = $schedule->occurrences()->orderBy('scheduled_date')->pluck('scheduled_date')->map(fn ($d) => Carbon::parse($d)->toDateString())->toArray();

        // 10-03 and 10-05 excluded; stops at 10-08
        $this->assertEquals([
            '2026-10-01',
            '2026-10-02',
            '2026-10-04',
            '2026-10-06',
            '2026-10-07',
            '2026-10-08',
        ], $dates);

        $this->assertFalse(in_array('2026-10-03', $dates, true));
        $this->assertFalse(in_array('2026-10-05', $dates, true));
        $this->assertFalse(in_array('2026-10-09', $dates, true));
    }

    public function test_timezone_and_daylight_saving_edge_cases(): void
    {
        // In America/New_York, DST transitions on Sunday November 1, 2026.
        // Scheduled local start time is 09:00:00 EDT / EST.
        $schedule = $this->manager->createSchedule($this->task, [
            'type' => 'daily',
            'start_date' => '2026-10-31',
            'end_date' => '2026-11-03',
            'start_time' => '09:00:00',
            'duration_minutes' => 60,
            'interval' => 1,
            'timezone' => 'America/New_York',
        ], Carbon::parse('2026-11-03', 'America/New_York'));

        $occurrences = $schedule->occurrences()->orderBy('scheduled_date')->get();

        $this->assertCount(4, $occurrences);

        // Oct 31 is EDT (UTC-4) -> 09:00 EDT = 13:00 UTC
        $oct31 = $occurrences->first(fn ($o) => Carbon::parse($o->scheduled_date)->toDateString() === '2026-10-31');
        $this->assertNotNull($oct31);
        $this->assertEquals('09:00:00', $oct31->start_time);
        $this->assertEquals('10:00:00', $oct31->end_time);
        $this->assertEquals('2026-10-31 13:00:00', Carbon::parse($oct31->utc_start_at)->toDateTimeString());
        $this->assertEquals('2026-10-31 14:00:00', Carbon::parse($oct31->utc_end_at)->toDateTimeString());

        // Nov 02 is EST (UTC-5) -> 09:00 EST = 14:00 UTC
        $nov02 = $occurrences->first(fn ($o) => Carbon::parse($o->scheduled_date)->toDateString() === '2026-11-02');
        $this->assertNotNull($nov02);
        $this->assertEquals('09:00:00', $nov02->start_time);
        $this->assertEquals('10:00:00', $nov02->end_time);
        $this->assertEquals('2026-11-02 14:00:00', Carbon::parse($nov02->utc_start_at)->toDateTimeString());
        $this->assertEquals('2026-11-02 15:00:00', Carbon::parse($nov02->utc_end_at)->toDateTimeString());
    }

    public function test_recurrence_changes_preserve_completed_historical_occurrences(): void
    {
        // Schedule daily starting 2026-10-01
        $schedule = $this->manager->createSchedule($this->task, [
            'type' => 'daily',
            'start_date' => '2026-10-01',
            'start_time' => '09:00:00',
            'duration_minutes' => 60,
            'interval' => 1,
            'timezone' => 'UTC',
        ], Carbon::parse('2026-10-10', 'UTC'));

        // Mark 2026-10-01 and 2026-10-03 as completed
        $occ1 = $schedule->occurrences()->where('scheduled_date', '2026-10-01')->first();
        $occ3 = $schedule->occurrences()->where('scheduled_date', '2026-10-03')->first();

        $this->manager->completeOccurrence($occ1);
        $this->manager->completeOccurrence($occ3);

        $this->assertEquals('completed', $occ1->fresh()->status);
        $this->assertEquals('completed', $occ3->fresh()->status);

        // Now edit the schedule rule from effective date 2026-10-05: change to weekly on Tuesdays only at 14:00
        $this->manager->updateScheduleRule($schedule, [
            'type' => 'weekly',
            'start_time' => '14:00:00',
            'weekdays' => [2], // Tuesdays
            'interval' => 1,
        ], Carbon::parse('2026-10-05', 'UTC'), Carbon::parse('2026-10-20', 'UTC'));

        // Completed historical occurrences must retain their original status, start_time, duration
        $this->assertEquals('completed', $occ1->fresh()->status);
        $this->assertEquals('09:00:00', $occ1->fresh()->start_time);
        $this->assertEquals(60, $occ1->fresh()->duration_minutes);

        $this->assertEquals('completed', $occ3->fresh()->status);
        $this->assertEquals('09:00:00', $occ3->fresh()->start_time);

        // Historical pending occurrence before 2026-10-05 (e.g. 2026-10-02) remains untouched
        $occ2 = $schedule->occurrences()->where('scheduled_date', '2026-10-02')->first();
        $this->assertEquals('pending', $occ2->fresh()->status);
        $this->assertEquals('09:00:00', $occ2->fresh()->start_time);

        // Future pending occurrences (e.g. 2026-10-06 Wednesday in original daily) should be superseded
        $supersededOccs = $schedule->occurrences()->where('status', 'cancelled')->get();
        $this->assertTrue($supersededOccs->isNotEmpty());
        $this->assertNotNull($supersededOccs->first()->superseded_at);

        // New weekly occurrences should have start_time 14:00:00 on Tuesdays (e.g. 2026-10-06 is Tue)
        $tuesdayOcc = $schedule->occurrences()->where('scheduled_date', '2026-10-06')->where('status', 'pending')->first();
        $this->assertNotNull($tuesdayOcc);
        $this->assertEquals('14:00:00', $tuesdayOcc->start_time);
    }

    public function test_future_replacement_and_preservation_of_occurrences_with_recorded_work(): void
    {
        $schedule = $this->manager->createSchedule($this->task, [
            'type' => 'daily',
            'start_date' => '2026-10-01',
            'start_time' => '09:00:00',
            'duration_minutes' => 60,
            'interval' => 1,
            'timezone' => 'UTC',
        ], Carbon::parse('2026-10-10', 'UTC'));

        // Occurrence on 2026-10-08 is in the future relative to effective date 2026-10-05
        $futureWorkedOcc = $schedule->occurrences()->where('scheduled_date', '2026-10-08')->first();
        $this->assertNotNull($futureWorkedOcc);

        // Record actual work session on this future occurrence
        $workSession = $this->manager->recordWorkSession($this->task, [
            'started_at' => '2026-10-08 09:00:00',
            'ended_at' => '2026-10-08 10:15:00',
            'duration_minutes' => 75,
            'notes' => 'Early exploratory sprint',
        ], $futureWorkedOcc);

        $this->assertTrue($futureWorkedOcc->hasRecordedWork());

        // Update recurrence rule from 2026-10-05 to weekly on Fridays only
        $this->manager->updateScheduleRule($schedule, [
            'type' => 'weekly',
            'start_time' => '15:00:00',
            'weekdays' => [5], // Friday
        ], Carbon::parse('2026-10-05', 'UTC'), Carbon::parse('2026-10-20', 'UTC'));

        // The future occurrence with recorded work MUST BE PRESERVED and not cancelled or deleted
        $futureWorkedOcc->refresh();
        $this->assertNotEquals('cancelled', $futureWorkedOcc->status);
        $this->assertNull($futureWorkedOcc->superseded_at);
        $this->assertEquals('2026-10-08', $futureWorkedOcc->scheduled_date->toDateString());
        $this->assertEquals('09:00:00', $futureWorkedOcc->start_time);

        // Work session remains fully intact
        $this->assertEquals(75, $workSession->fresh()->duration_minutes);
        $this->assertEquals($futureWorkedOcc->id, $workSession->fresh()->schedule_occurrence_id);
    }

    public function test_one_occurrence_edits_record_exception_and_preserve_original_info(): void
    {
        $schedule = $this->manager->createSchedule($this->task, [
            'type' => 'daily',
            'start_date' => '2026-10-10',
            'start_time' => '09:00:00',
            'duration_minutes' => 60,
            'timezone' => 'UTC',
        ], Carbon::parse('2026-10-15', 'UTC'));

        $occurrence = $schedule->occurrences()->where('scheduled_date', '2026-10-12')->first();
        $this->assertFalse($occurrence->is_exception);

        // Reschedule this specific occurrence to 2026-10-12 at 15:30 with 90 mins duration
        $updated = $this->manager->editSingleOccurrence($occurrence, [
            'start_time' => '15:30:00',
            'duration_minutes' => 90,
            'notes' => 'Rescheduled doctor appointment conflict',
        ]);

        $this->assertTrue($updated->is_exception);
        $this->assertEquals('2026-10-12', $updated->original_scheduled_date->toDateString());
        $this->assertEquals('09:00:00', $updated->original_start_time);
        $this->assertEquals(60, $updated->original_duration_minutes);
        $this->assertEquals('15:30:00', $updated->start_time);
        $this->assertEquals(90, $updated->duration_minutes);
        $this->assertEquals('17:00:00', $updated->end_time);

        // Re-running generation must not overwrite this exception
        $this->generator->generate($schedule, Carbon::parse('2026-10-15', 'UTC'));

        $updated->refresh();
        $this->assertTrue($updated->is_exception);
        $this->assertEquals('15:30:00', $updated->start_time);
        $this->assertEquals(90, $updated->duration_minutes);
    }

    public function test_idempotent_generation_and_duplicate_prevention(): void
    {
        $schedule = $this->manager->createSchedule($this->task, [
            'type' => 'weekly',
            'start_date' => '2026-10-01',
            'start_time' => '09:00:00',
            'duration_minutes' => 60,
            'weekdays' => [1, 2, 3, 4, 5],
            'timezone' => 'UTC',
        ], Carbon::parse('2026-10-31', 'UTC'));

        $initialCount = $schedule->occurrences()->count();

        // Run generation 5 more times consecutively
        for ($i = 0; $i < 5; $i++) {
            $this->generator->generate($schedule, Carbon::parse('2026-10-31', 'UTC'));
        }

        $afterCount = $schedule->occurrences()->count();
        $this->assertEquals($initialCount, $afterCount);

        // Check no duplicate dates exist for this schedule
        $dates = $schedule->occurrences()->pluck('scheduled_date')->map(fn ($d) => Carbon::parse($d)->toDateString())->toArray();
        $this->assertCount(count(array_unique($dates)), $dates);
    }

    public function test_ownership_and_authorization(): void
    {
        $otherUser = User::factory()->create();
        $otherTask = Task::factory()->create(['user_id' => $otherUser->id]);

        $otherSchedule = $this->manager->createSchedule($otherTask, [
            'type' => 'daily',
            'start_date' => '2026-10-01',
            'start_time' => '09:00:00',
            'duration_minutes' => 60,
            'timezone' => 'UTC',
        ], Carbon::parse('2026-10-05', 'UTC'));

        $otherOccurrence = $otherSchedule->occurrences()->first();

        // Acting as $this->user: attempting to modify $otherUser's schedule via HTTP
        $response = $this->actingAs($this->user)->put("/schedules/{$otherSchedule->id}", [
            'duration_minutes' => 120,
        ]);
        $response->assertForbidden();

        // Attempting to modify $otherUser's occurrence
        $response = $this->actingAs($this->user)->put("/occurrences/{$otherOccurrence->id}", [
            'duration_minutes' => 120,
        ]);
        $response->assertForbidden();

        // Attempting to complete $otherUser's occurrence
        $response = $this->actingAs($this->user)->patch("/occurrences/{$otherOccurrence->id}/complete");
        $response->assertForbidden();

        // Attempting to record work session on $otherUser's task
        $response = $this->actingAs($this->user)->post('/work-sessions', [
            'task_id' => $otherTask->id,
            'started_at' => now()->toDateTimeString(),
            'duration_minutes' => 30,
        ]);
        $response->assertForbidden();
    }

    public function test_task_has_history_prevents_deletion_when_work_or_occurrences_exist(): void
    {
        $task = Task::factory()->create(['user_id' => $this->user->id, 'has_history' => false]);
        $this->assertFalse($task->hasHistory());

        $schedule = $this->manager->createSchedule($task, [
            'type' => 'daily',
            'start_date' => '2026-10-01',
            'start_time' => '09:00:00',
            'duration_minutes' => 60,
            'timezone' => 'UTC',
        ], Carbon::parse('2026-10-05', 'UTC'));

        $occ = $schedule->occurrences()->first();

        // Once completed, task has history
        $this->manager->completeOccurrence($occ);
        $this->assertTrue($task->fresh()->hasHistory());

        // Deleting task with history redirects with validation error or fails permanent deletion
        $response = $this->actingAs($this->user)->delete("/tasks/{$task->id}");
        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
    }

    public function test_user_flow_schedule_complete_work_unscheduled_work_reopen_and_verify_records_intact(): void
    {
        // 1. Schedule two hours of task
        $response = $this->actingAs($this->user)->post("/tasks/{$this->task->id}/schedules", [
            'type' => 'one_time',
            'start_date' => '2026-10-10',
            'start_time' => '10:00:00',
            'duration_minutes' => 120, // 2 hours
            'timezone' => 'America/New_York',
        ]);
        $response->assertRedirect();

        $occurrence = ScheduleOccurrence::where('task_id', $this->task->id)->firstOrFail();
        $this->assertEquals(120, $occurrence->duration_minutes);
        $this->assertEquals('pending', $occurrence->status);

        // 2. Mark the occurrence complete
        $completeResponse = $this->actingAs($this->user)->patch("/occurrences/{$occurrence->id}/complete");
        $completeResponse->assertRedirect();
        $this->assertEquals('completed', $occurrence->fresh()->status);
        $this->assertNotNull($occurrence->fresh()->completed_at);

        // 3. Record two hours of actual work
        $workResponse1 = $this->actingAs($this->user)->post('/work-sessions', [
            'task_id' => $this->task->id,
            'schedule_occurrence_id' => $occurrence->id,
            'started_at' => '2026-10-10 10:00:00',
            'duration_minutes' => 120,
            'notes' => 'Two hours of scheduled focused work',
        ]);
        $workResponse1->assertRedirect();

        $this->assertDatabaseHas('work_sessions', [
            'task_id' => $this->task->id,
            'schedule_occurrence_id' => $occurrence->id,
            'duration_minutes' => 120,
        ]);

        // 4. Add another one-hour work session on an unscheduled day
        $workResponse2 = $this->actingAs($this->user)->post('/work-sessions', [
            'task_id' => $this->task->id,
            'schedule_occurrence_id' => null,
            'started_at' => '2026-10-12 14:00:00',
            'duration_minutes' => 60,
            'notes' => 'Ad-hoc 1 hour work on unscheduled day',
        ]);
        $workResponse2->assertRedirect();

        $this->assertDatabaseHas('work_sessions', [
            'task_id' => $this->task->id,
            'schedule_occurrence_id' => null,
            'duration_minutes' => 60,
        ]);

        // 5. Reopen the occurrence
        $reopenResponse = $this->actingAs($this->user)->patch("/occurrences/{$occurrence->id}/reopen");
        $reopenResponse->assertRedirect();

        $occurrenceFresh = $occurrence->fresh();
        $this->assertEquals('pending', $occurrenceFresh->status);
        $this->assertNull($occurrenceFresh->completed_at);

        // 6. Verify that all work records remain intact
        $workSessions = WorkSession::where('task_id', $this->task->id)->orderBy('started_at')->get();
        $this->assertCount(2, $workSessions);

        // First session: 120 mins, still linked to occurrence
        $this->assertEquals(120, $workSessions[0]->duration_minutes);
        $this->assertEquals($occurrence->id, $workSessions[0]->schedule_occurrence_id);
        $this->assertEquals('Two hours of scheduled focused work', $workSessions[0]->notes);

        // Second session: 60 mins, unscheduled
        $this->assertEquals(60, $workSessions[1]->duration_minutes);
        $this->assertNull($workSessions[1]->schedule_occurrence_id);
        $this->assertEquals('Ad-hoc 1 hour work on unscheduled day', $workSessions[1]->notes);

        // Occurrence still has its work session relationship intact
        $this->assertCount(1, $occurrenceFresh->workSessions);
        $this->assertEquals(120, $occurrenceFresh->workSessions->first()->duration_minutes);
    }

    public function test_sync_multiple_weekly_schedule_rules_with_different_times_and_days(): void
    {
        // Set up Monday 14:00-16:00 (2-4 PM) and Tuesday 10:00-12:00 (10-12 AM)
        $response = $this->actingAs($this->user)->post("/tasks/{$this->task->id}/schedules/sync", [
            'start_date' => '2026-10-12', // Monday
            'timezone' => 'UTC',
            'rules' => [
                [
                    'type' => 'weekly',
                    'weekdays' => [1], // Monday
                    'start_time' => '14:00:00',
                    'duration_minutes' => 120,
                ],
                [
                    'type' => 'weekly',
                    'weekdays' => [2], // Tuesday
                    'start_time' => '10:00:00',
                    'duration_minutes' => 120,
                ],
            ],
        ]);
        $response->assertRedirect();

        // Verify two active schedules created
        $schedules = RecurringSchedule::where('task_id', $this->task->id)->where('is_active', true)->get();
        $this->assertCount(2, $schedules);

        // Verify occurrences for Monday at 14:00 and Tuesday at 10:00 exist
        $monOccurrence = ScheduleOccurrence::where('task_id', $this->task->id)
            ->where('scheduled_date', '2026-10-12')
            ->firstOrFail();
        $this->assertEquals('14:00:00', $monOccurrence->start_time);
        $this->assertEquals(120, $monOccurrence->duration_minutes);

        $tueOccurrence = ScheduleOccurrence::where('task_id', $this->task->id)
            ->where('scheduled_date', '2026-10-13')
            ->firstOrFail();
        $this->assertEquals('10:00:00', $tueOccurrence->start_time);
        $this->assertEquals(120, $tueOccurrence->duration_minutes);

        // Now update / sync with empty rules to unschedule
        $clearResponse = $this->actingAs($this->user)->post("/tasks/{$this->task->id}/schedules/sync", [
            'start_date' => '2026-10-12',
            'timezone' => 'UTC',
            'rules' => [],
        ]);
        $clearResponse->assertRedirect();

        // Both previous schedules deactivated
        $activeCount = RecurringSchedule::where('task_id', $this->task->id)->where('is_active', true)->count();
        $this->assertEquals(0, $activeCount);

        // Future unworked pending occurrences are cancelled
        $this->assertEquals('cancelled', $monOccurrence->fresh()->status);
        $this->assertEquals('cancelled', $tueOccurrence->fresh()->status);
    }

    public function test_sync_monthly_rules_and_one_time_without_end_date(): void
    {
        // 1. One-time schedule without an end date: single date 2026-10-15
        $oneTimeResponse = $this->actingAs($this->user)->post("/tasks/{$this->task->id}/schedules/sync", [
            'start_date' => '2026-10-15',
            'end_date' => null,
            'timezone' => 'UTC',
            'rules' => [
                [
                    'type' => 'one_time',
                    'start_date' => '2026-10-15',
                    'end_date' => null,
                    'start_time' => '15:00',
                    'duration_minutes' => 90,
                ],
            ],
        ]);
        $oneTimeResponse->assertRedirect();

        $oneTimeOccurrences = ScheduleOccurrence::where('task_id', $this->task->id)
            ->where('status', 'pending')
            ->get();
        $this->assertCount(1, $oneTimeOccurrences);
        $this->assertEquals('2026-10-15', $oneTimeOccurrences->first()->scheduled_date->toDateString());
        $this->assertEquals('15:00:00', $oneTimeOccurrences->first()->start_time);

        // 2. Monthly on the 7th of the month
        $monthlyDateResponse = $this->actingAs($this->user)->post("/tasks/{$this->task->id}/schedules/sync", [
            'start_date' => '2026-10-01',
            'effective_date' => '2026-10-01',
            'end_date' => null,
            'timezone' => 'UTC',
            'rules' => [
                [
                    'type' => 'monthly_date',
                    'start_date' => '2026-10-01',
                    'end_date' => null,
                    'start_time' => '11:00',
                    'duration_minutes' => 60,
                    'month_day' => 7,
                ],
            ],
        ]);
        $monthlyDateResponse->assertRedirect();

        $oct7Occurrence = ScheduleOccurrence::where('task_id', $this->task->id)
            ->where('scheduled_date', '2026-10-07')
            ->where('status', 'pending')
            ->first();
        $this->assertNotNull($oct7Occurrence);
        $this->assertEquals('11:00:00', $oct7Occurrence->start_time);

        // 3. Monthly on the 1st Monday of the month
        $monthlyDayResponse = $this->actingAs($this->user)->post("/tasks/{$this->task->id}/schedules/sync", [
            'start_date' => '2026-10-01',
            'effective_date' => '2026-10-01',
            'end_date' => null,
            'timezone' => 'UTC',
            'rules' => [
                [
                    'type' => 'monthly_day',
                    'start_date' => '2026-10-01',
                    'end_date' => null,
                    'start_time' => '09:00',
                    'duration_minutes' => 120,
                    'month_week' => 1,
                    'month_weekday' => 1, // 1st Monday
                ],
            ],
        ]);
        $monthlyDayResponse->assertRedirect();

        // In Oct 2026, 1st Monday is Oct 5
        $firstMonOccurrence = ScheduleOccurrence::where('task_id', $this->task->id)
            ->where('scheduled_date', '2026-10-05')
            ->where('status', 'pending')
            ->first();
        $this->assertNotNull($firstMonOccurrence);
        $this->assertEquals('09:00:00', $firstMonOccurrence->start_time);
    }
}

