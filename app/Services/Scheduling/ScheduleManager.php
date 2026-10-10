<?php

namespace App\Services\Scheduling;

use App\Models\RecurringSchedule;
use App\Models\ScheduleOccurrence;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkSession;
use App\Services\Reporting\ReportCacheService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ScheduleManager
{
    public function __construct(
        protected ScheduleOccurrenceGenerator $generator
    ) {}

    /**
     * Create a new recurring schedule and generate its initial window of occurrences.
     */
    public function createSchedule(Task $task, array $data, ?Carbon $horizon = null): RecurringSchedule
    {
        return DB::transaction(function () use ($task, $data, $horizon) {
            $tz = $data['timezone'] ?? $task->user->timezone ?? 'UTC';

            $schedule = RecurringSchedule::create([
                'user_id' => $task->user_id,
                'task_id' => $task->id,
                'type' => $data['type'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'] ?? null,
                'start_time' => $data['start_time'],
                'duration_minutes' => $data['duration_minutes'] ?? 60,
                'timezone' => $tz,
                'interval' => $data['interval'] ?? 1,
                'weekdays' => $data['weekdays'] ?? null,
                'month_day' => $data['month_day'] ?? null,
                'month_week' => $data['month_week'] ?? null,
                'month_weekday' => $data['month_weekday'] ?? null,
                'selected_dates' => $data['selected_dates'] ?? null,
                'exclusions' => $data['exclusions'] ?? [],
                'is_active' => true,
                'version' => 1,
            ]);

            $this->generator->generate($schedule, $horizon);
            ReportCacheService::invalidateUser($task->user_id);

            return $schedule->fresh(['occurrences']);
        });
    }

    /**
     * Synchronize all recurring schedule rules for a task.
     * Deactivates existing rules (superseding untouched future pending occurrences) and creates the new rules.
     */
    public function syncTaskSchedules(Task $task, array $data, ?Carbon $effectiveDate = null, ?Carbon $horizon = null): Collection
    {
        return DB::transaction(function () use ($task, $data, $effectiveDate, $horizon) {
            $tz = $data['timezone'] ?? $task->user->timezone ?? 'UTC';
            $effective = $effectiveDate ? $effectiveDate->copy()->setTimezone($tz)->startOfDay() : now($tz)->startOfDay();

            // 1. Deactivate existing active recurring schedules for this task
            $activeSchedules = RecurringSchedule::where('task_id', $task->id)
                ->where('is_active', true)
                ->lockForUpdate()
                ->get();

            foreach ($activeSchedules as $oldSchedule) {
                $this->deactivateSchedule($oldSchedule, $effective);
            }

            // 2. Create each new schedule rule
            $rules = $data['rules'] ?? [];
            $created = new Collection();
            $startDate = $data['start_date'] ?? $effective->toDateString();
            $endDate = $data['end_date'] ?? null;

            foreach ($rules as $ruleData) {
                $schedule = RecurringSchedule::create([
                    'user_id' => $task->user_id,
                    'task_id' => $task->id,
                    'type' => $ruleData['type'] ?? 'weekly',
                    'start_date' => $ruleData['start_date'] ?? $startDate,
                    'end_date' => $ruleData['end_date'] ?? $endDate,
                    'start_time' => $ruleData['start_time'],
                    'duration_minutes' => max(1, (int) ($ruleData['duration_minutes'] ?? 60)),
                    'timezone' => $tz,
                    'interval' => $ruleData['interval'] ?? 1,
                    'weekdays' => $ruleData['weekdays'] ?? null,
                    'month_day' => $ruleData['month_day'] ?? null,
                    'month_week' => $ruleData['month_week'] ?? null,
                    'month_weekday' => $ruleData['month_weekday'] ?? null,
                    'is_active' => true,
                    'version' => 1,
                ]);

                $this->generator->generate($schedule, $horizon, $effective);
                $created->push($schedule);
            }

            ReportCacheService::invalidateUser($task->user_id);

            return $created;
        });
    }

    /**
     * Update an entire recurring schedule rule.
     *
     * HISTORICAL RULES ENFORCEMENT:
     * - Past occurrences remain untouched.
     * - Completed occurrences remain untouched.
     * - Any occurrence with recorded work sessions remains untouched, even if scheduled in the future.
     * - Applicable future pending occurrences are superseded/cancelled (not deleted) to preserve audit history.
     * - New occurrences are generated from the updated rule starting from the effective date.
     */
    public function updateScheduleRule(RecurringSchedule $schedule, array $data, ?Carbon $effectiveDate = null, ?Carbon $horizon = null): RecurringSchedule
    {
        return DB::transaction(function () use ($schedule, $data, $effectiveDate, $horizon) {
            /** @var RecurringSchedule $lockedSchedule */
            $lockedSchedule = RecurringSchedule::where('id', $schedule->id)->lockForUpdate()->firstOrFail();

            $tz = $lockedSchedule->timezone ?: 'UTC';
            $effective = $effectiveDate ? $effectiveDate->copy()->setTimezone($tz)->startOfDay() : now($tz)->startOfDay();
            $effectiveDateStr = $effective->toDateString();

            // Find future occurrences attached to this schedule from the effective date
            $futureOccurrences = ScheduleOccurrence::where('recurring_schedule_id', $lockedSchedule->id)
                ->where('scheduled_date', '>=', $effectiveDateStr)
                ->with('workSessions')
                ->lockForUpdate()
                ->get();

            foreach ($futureOccurrences as $occ) {
                // RULE: Preserve completed occurrences
                if ($occ->isCompleted()) {
                    continue;
                }

                // RULE: Preserve any occurrence with recorded work sessions, even if scheduled in the future
                if ($occ->workSessions->isNotEmpty()) {
                    continue;
                }

                // RULE: Preserve manual one-off exceptions
                if ($occ->is_exception) {
                    continue;
                }

                // RULE: Supersede / cancel replaced future occurrences instead of deleting their audit history
                if ($occ->status === 'pending') {
                    $occ->update([
                        'status' => 'cancelled',
                        'superseded_at' => now(),
                    ]);
                }
            }

            // Update schedule rule parameters and bump version
            $updatePayload = array_intersect_key($data, array_flip([
                'type',
                'start_date',
                'end_date',
                'start_time',
                'duration_minutes',
                'timezone',
                'interval',
                'weekdays',
                'month_day',
                'month_week',
                'month_weekday',
                'selected_dates',
                'exclusions',
                'is_active',
            ]));

            $updatePayload['version'] = $lockedSchedule->version + 1;

            $lockedSchedule->update($updatePayload);

            // Generate new occurrences starting from effectiveDate up to horizon
            $this->generator->generate($lockedSchedule, $horizon, $effective);

            ReportCacheService::invalidateUser($lockedSchedule->user_id);

            return $lockedSchedule->fresh(['occurrences']);
        });
    }

    /**
     * Edit a single occurrence, recording the exception and preserving original schedule info.
     */
    public function editSingleOccurrence(ScheduleOccurrence $occurrence, array $data): ScheduleOccurrence
    {
        return DB::transaction(function () use ($occurrence, $data) {
            /** @var ScheduleOccurrence $lockedOccurrence */
            $lockedOccurrence = ScheduleOccurrence::where('id', $occurrence->id)->lockForUpdate()->firstOrFail();

            $originalDate = $lockedOccurrence->original_scheduled_date
                ? $lockedOccurrence->original_scheduled_date->toDateString()
                : $lockedOccurrence->scheduled_date->toDateString();

            $originalTime = $lockedOccurrence->original_start_time ?: $lockedOccurrence->start_time;
            $originalDuration = $lockedOccurrence->original_duration_minutes ?: $lockedOccurrence->duration_minutes;

            $newDate = $data['scheduled_date'] ?? $lockedOccurrence->scheduled_date->toDateString();
            $newStartTime = Carbon::parse($data['start_time'] ?? $lockedOccurrence->start_time)->format('H:i:s');
            $newDuration = isset($data['duration_minutes']) ? max(1, (int) $data['duration_minutes']) : $lockedOccurrence->duration_minutes;

            $tz = $lockedOccurrence->timezone ?: 'UTC';
            $localStart = Carbon::createFromFormat('Y-m-d H:i:s', "{$newDate} {$newStartTime}", $tz);
            $localEnd = $localStart->copy()->addMinutes($newDuration);

            // If rescheduled to a new date, exclude old date from recurring schedule to prevent duplicate generation
            if ($newDate !== $originalDate && $lockedOccurrence->recurring_schedule_id) {
                $schedule = $lockedOccurrence->recurringSchedule;
                if ($schedule) {
                    $exclusions = $schedule->exclusions ?? [];
                    if (! in_array($originalDate, $exclusions, true)) {
                        $exclusions[] = $originalDate;
                        $schedule->update(['exclusions' => $exclusions]);
                    }
                }
            }

            $lockedOccurrence->update([
                'is_exception' => true,
                'original_scheduled_date' => $originalDate,
                'original_start_time' => $originalTime,
                'original_duration_minutes' => $originalDuration,
                'scheduled_date' => $newDate,
                'start_time' => $newStartTime,
                'end_time' => $localEnd->format('H:i:s'),
                'duration_minutes' => $newDuration,
                'utc_start_at' => $localStart->copy()->setTimezone('UTC')->toDateTimeString(),
                'utc_end_at' => $localEnd->copy()->setTimezone('UTC')->toDateTimeString(),
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $lockedOccurrence->notes,
            ]);

            ReportCacheService::invalidateUser($lockedOccurrence->user_id);

            return $lockedOccurrence->fresh(['task', 'recurringSchedule']);
        });
    }

    /**
     * Mark an occurrence as completed.
     */
    public function completeOccurrence(ScheduleOccurrence $occurrence, ?Carbon $completedAt = null): ScheduleOccurrence
    {
        $occurrence->markCompleted($completedAt);
        ReportCacheService::invalidateUser($occurrence->user_id);

        return $occurrence->fresh();
    }

    /**
     * Mark an occurrence as skipped.
     */
    public function skipOccurrence(ScheduleOccurrence $occurrence): ScheduleOccurrence
    {
        $occurrence->markSkipped();
        ReportCacheService::invalidateUser($occurrence->user_id);

        return $occurrence->fresh();
    }

    /**
     * Deactivate a schedule and supersede all future pending unworked occurrences.
     */
    public function deactivateSchedule(RecurringSchedule $schedule, ?Carbon $effectiveDate = null): RecurringSchedule
    {
        return DB::transaction(function () use ($schedule, $effectiveDate) {
            /** @var RecurringSchedule $lockedSchedule */
            $lockedSchedule = RecurringSchedule::where('id', $schedule->id)->lockForUpdate()->firstOrFail();

            $tz = $lockedSchedule->timezone ?: 'UTC';
            $effective = $effectiveDate ? $effectiveDate->copy()->setTimezone($tz)->startOfDay() : now($tz)->startOfDay();
            $effectiveDateStr = $effective->toDateString();

            ScheduleOccurrence::where('recurring_schedule_id', $lockedSchedule->id)
                ->where('scheduled_date', '>=', $effectiveDateStr)
                ->where('status', 'pending')
                ->whereDoesntHave('workSessions')
                ->where('is_exception', false)
                ->update([
                    'status' => 'cancelled',
                    'superseded_at' => now(),
                ]);

            $lockedSchedule->update(['is_active' => false]);
            ReportCacheService::invalidateUser($lockedSchedule->user_id);

            return $lockedSchedule->fresh();
        });
    }

    /**
     * Record an actual work session.
     */
    public function recordWorkSession(Task $task, array $data, ?ScheduleOccurrence $occurrence = null): WorkSession
    {
        return DB::transaction(function () use ($task, $data, $occurrence) {
            $session = WorkSession::create([
                'user_id' => $task->user_id,
                'task_id' => $task->id,
                'schedule_occurrence_id' => $occurrence?->id,
                'started_at' => $data['started_at'],
                'ended_at' => $data['ended_at'] ?? null,
                'duration_minutes' => (int) $data['duration_minutes'],
                'notes' => $data['notes'] ?? null,
                'is_manual' => (bool) ($data['is_manual'] ?? false),
            ]);

            // Optional explicit completion on occurrence
            if (! empty($data['mark_completed']) && $occurrence) {
                $occurrence->markCompleted();
            }

            ReportCacheService::invalidateUser($task->user_id);

            return $session;
        });
    }

    /**
     * Fetch occurrences for a user within a date range, auto-extending generation if needed.
     *
     * @return Collection<int, ScheduleOccurrence>
     */
    public function getOccurrencesForRange(User $user, Carbon $startDate, Carbon $endDate, ?int $taskId = null): Collection
    {
        $tz = $user->timezone ?: 'UTC';
        $rangeStart = $startDate->copy()->setTimezone($tz)->startOfDay();
        $rangeEnd = $endDate->copy()->setTimezone($tz)->endOfDay();

        // Auto-extend generation for user's active schedules up to rangeEnd without requiring background daemon
        $schedules = RecurringSchedule::where('user_id', $user->id)
            ->where('is_active', true)
            ->when($taskId, fn ($q) => $q->where('task_id', $taskId))
            ->get();

        foreach ($schedules as $sched) {
            $lastGenerated = $sched->last_generated_until ? Carbon::parse($sched->last_generated_until, $tz) : null;
            if (! $lastGenerated || $lastGenerated->lessThan($rangeEnd)) {
                $this->generator->generate($sched, $rangeEnd);
            }
        }

        return ScheduleOccurrence::where('user_id', $user->id)
            ->when($taskId, fn ($q) => $q->where('task_id', $taskId))
            ->whereBetween('scheduled_date', [$rangeStart->toDateString(), $rangeEnd->toDateString()])
            ->where('status', '!=', 'cancelled')
            ->with([
                'task:id,title,status',
                'recurringSchedule:id,task_id,type,start_time,duration_minutes',
                'workSessions:id,user_id,task_id,schedule_occurrence_id,duration_minutes,started_at,ended_at',
            ])
            ->orderBy('scheduled_date')
            ->orderBy('start_time')
            ->get();
    }
}
