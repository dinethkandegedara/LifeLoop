<?php

namespace App\Services\Reporting;

use App\Models\ScheduleOccurrence;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkSession;
use App\Services\Scheduling\ScheduleManager;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ScheduleReportingService
{
    public function __construct(
        protected ScheduleManager $scheduleManager
    ) {}

    /**
     * Compute normalized date boundaries for Today, Week, Month, or Custom range in user timezone.
     *
     * @return array{start: Carbon, end: Carbon, label: string, period: string}
     */
    public function getPeriodBoundaries(
        User $user,
        string $period = 'week',
        ?Carbon $customStart = null,
        ?Carbon $customEnd = null,
        ?Carbon $anchorDate = null
    ): array {
        $tz = $user->timezone ?: 'UTC';
        $today = now($tz)->startOfDay();
        $target = $anchorDate ? $anchorDate->copy()->setTimezone($tz)->startOfDay() : $today->copy();

        switch ($period) {
            case 'today':
                $start = $target->copy();
                $end = $target->copy()->endOfDay();
                if ($start->toDateString() === $today->toDateString()) {
                    $label = 'Today (' . $start->format('M d, Y') . ')';
                } elseif ($start->toDateString() === $today->copy()->subDay()->toDateString()) {
                    $label = 'Yesterday (' . $start->format('M d, Y') . ')';
                } else {
                    $label = $start->format('D, M d, Y');
                }
                $isCurrent = $start->toDateString() === $today->toDateString();
                $prevDate = $target->copy()->subDay()->toDateString();
                $nextDate = $target->copy()->addDay()->toDateString();
                $canGoNext = $target->copy()->addDay()->lte($today);
                break;

            case 'month':
                $start = $target->copy()->startOfMonth();
                $monthEnd = $target->copy()->endOfMonth();
                $isCurrentMonth = $start->format('Y-m') === $today->format('Y-m');

                if ($isCurrentMonth && $today->isBefore($monthEnd)) {
                    $end = $today->copy()->endOfDay();
                    $label = $start->format('F Y') . ' (To Date: ' . $start->format('M d') . ' – ' . $end->format('M d') . ')';
                } else {
                    $end = $monthEnd;
                    $label = $start->format('F Y');
                }

                $isCurrent = $isCurrentMonth;
                $prevDate = $target->copy()->subMonth()->toDateString();
                $nextDate = $target->copy()->addMonth()->toDateString();
                $canGoNext = $target->copy()->addMonth()->startOfMonth()->lte($today);
                break;

            case 'all':
                // All-time: from 1 year ago to today
                $start = $today->copy()->subYear()->startOfDay();
                $end = $today->copy()->endOfDay();
                $label = 'All Time';
                $isCurrent = true;
                $prevDate = null;
                $nextDate = null;
                $canGoNext = false;
                break;

            case 'custom':
                $start = $customStart ? $customStart->copy()->setTimezone($tz)->startOfDay() : $today->copy()->startOfWeek();
                $end = $customEnd ? $customEnd->copy()->setTimezone($tz)->endOfDay() : $today->copy()->endOfWeek();
                $label = $start->format('M d, Y') . ' – ' . $end->format('M d, Y');
                $isCurrent = false;
                $prevDate = null;
                $nextDate = null;
                $canGoNext = false;
                break;

            case 'week':
            default:
                // Monday to Sunday standard week
                $start = $target->copy()->startOfWeek();
                $weekEnd = $target->copy()->endOfWeek();
                $isCurrentWeek = $start->lte($today) && $today->lte($weekEnd);

                if ($isCurrentWeek && $today->isBefore($weekEnd)) {
                    $end = $today->copy()->endOfDay();
                    $label = 'This Week (' . $start->format('M d') . ($start->toDateString() !== $end->toDateString() ? ' – ' . $end->format('M d, Y') : ', ' . $start->format('Y')) . ')';
                } elseif ($start->toDateString() === $today->copy()->subWeek()->startOfWeek()->toDateString()) {
                    $end = $weekEnd;
                    $label = 'Last Week (' . $start->format('M d') . ' – ' . $end->format('M d, Y') . ')';
                } else {
                    $end = $weekEnd;
                    $label = 'Week of ' . $start->format('M d') . ' – ' . $end->format('M d, Y');
                }

                $period = 'week';
                $isCurrent = $isCurrentWeek;
                $prevDate = $target->copy()->subWeek()->toDateString();
                $nextDate = $target->copy()->addWeek()->toDateString();
                $canGoNext = $target->copy()->addWeek()->startOfWeek()->lte($today);
                break;
        }

        return [
            'start' => $start,
            'end' => $end,
            'label' => $label,
            'period' => $period,
            'anchor_date' => $target->toDateString(),
            'is_current' => $isCurrent,
            'prev_date' => $prevDate,
            'next_date' => $nextDate,
            'can_go_next' => $canGoNext,
        ];
    }

    /**
     * Calculate core scheduling and performance metrics for a user in a date range.
     *
     * @return array<string, mixed>
     */
    public function getMetrics(User $user, Carbon $startDate, Carbon $endDate, ?int $taskId = null): array
    {
        $tz = $user->timezone ?: 'UTC';
        $now = now($tz);
        $todayDate = $now->toDateString();
        $currentTimeStr = $now->format('H:i:s');

        $startDateStr = $startDate->toDateString();
        $endDateStr = $endDate->toDateString();

        // Auto-generate future occurrences if horizon extends past today
        if ($endDate->isFuture()) {
            $this->scheduleManager->getOccurrencesForRange($user, $startDate, $endDate, $taskId);
        }

        // 1. Aggregation on ScheduleOccurrences (eligible occurrences exclude cancelled and skipped)
        $occurrenceStats = ScheduleOccurrence::where('user_id', $user->id)
            ->where('status', '!=', 'cancelled')
            ->whereBetween('scheduled_date', [$startDateStr, $endDateStr])
            ->when($taskId, fn ($q) => $q->where('task_id', $taskId))
            ->selectRaw("
                COALESCE(SUM(CASE WHEN status IN ('pending', 'completed') THEN duration_minutes ELSE 0 END), 0) as scheduled_minutes,
                COALESCE(SUM(CASE WHEN status = 'completed' THEN duration_minutes ELSE 0 END), 0) as completed_minutes,
                COALESCE(SUM(CASE WHEN status = 'pending' THEN duration_minutes ELSE 0 END), 0) as remaining_minutes,
                COALESCE(SUM(CASE WHEN status = 'skipped' THEN duration_minutes ELSE 0 END), 0) as skipped_minutes,
                COUNT(CASE WHEN status IN ('pending', 'completed') THEN 1 END) as scheduled_count,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_count,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as incomplete_count,
                COUNT(CASE WHEN status = 'skipped' THEN 1 END) as skipped_count,
                COUNT(*) as total_count
            ")
            ->first();

        $scheduledMinutes = (int) ($occurrenceStats->scheduled_minutes ?? 0);
        $completedMinutes = (int) ($occurrenceStats->completed_minutes ?? 0);
        $remainingMinutes = (int) ($occurrenceStats->remaining_minutes ?? 0);
        $skippedMinutes = (int) ($occurrenceStats->skipped_minutes ?? 0);

        $scheduledCount = (int) ($occurrenceStats->scheduled_count ?? 0);
        $completedCount = (int) ($occurrenceStats->completed_count ?? 0);
        $incompleteCount = (int) ($occurrenceStats->incomplete_count ?? 0);
        $skippedCount = (int) ($occurrenceStats->skipped_count ?? 0);
        $totalCount = (int) ($occurrenceStats->total_count ?? 0);

        // 2. Aggregation on WorkSessions (attributed to actual started_at date in user timezone)
        $rangeStartDateTime = $startDate->copy()->startOfDay()->toDateTimeString();
        $rangeEndDateTime = $endDate->copy()->endOfDay()->toDateTimeString();

        $workStats = WorkSession::where('user_id', $user->id)
            ->whereBetween('started_at', [$rangeStartDateTime, $rangeEndDateTime])
            ->when($taskId, fn ($q) => $q->where('task_id', $taskId))
            ->selectRaw("
                COALESCE(SUM(duration_minutes), 0) as actual_minutes,
                COALESCE(SUM(CASE WHEN schedule_occurrence_id IS NULL THEN duration_minutes ELSE 0 END), 0) as unscheduled_minutes,
                COALESCE(SUM(CASE WHEN schedule_occurrence_id IS NOT NULL THEN duration_minutes ELSE 0 END), 0) as scheduled_actual_minutes,
                COUNT(*) as session_count
            ")
            ->first();

        $actualMinutes = (int) ($workStats->actual_minutes ?? 0);
        $unscheduledMinutes = (int) ($workStats->unscheduled_minutes ?? 0);
        $scheduledActualMinutes = (int) ($workStats->scheduled_actual_minutes ?? 0);
        $workSessionCount = (int) ($workStats->session_count ?? 0);

        // 3. Overdue incomplete occurrences:
        // Rule: Overdue occurrences remain incomplete until completed or explicitly skipped.
        // Rule: Future incomplete occurrences count as remaining scheduled work, not overdue work.
        $overdueQuery = ScheduleOccurrence::where('user_id', $user->id)
            ->where('status', 'pending')
            ->whereBetween('scheduled_date', [$startDateStr, $endDateStr])
            ->when($taskId, fn ($q) => $q->where('task_id', $taskId))
            ->where(function ($q) use ($todayDate, $currentTimeStr) {
                $q->where('scheduled_date', '<', $todayDate)
                    ->orWhere(function ($q2) use ($todayDate, $currentTimeStr) {
                        $q2->where('scheduled_date', '=', $todayDate)
                            ->where('start_time', '<=', $currentTimeStr);
                    });
            });

        $overdueCount = $overdueQuery->count();
        $overdueOccurrences = $overdueQuery->with(['task:id,title', 'recurringSchedule'])
            ->orderBy('scheduled_date')
            ->orderBy('start_time')
            ->limit(20)
            ->get();

        // 4. Rate calculations (division-by-zero protected)
        $scheduledHours = round($scheduledMinutes / 60, 2);
        $completedPlannedHours = round($completedMinutes / 60, 2);
        $remainingScheduledHours = round($remainingMinutes / 60, 2);
        $skippedHours = round($skippedMinutes / 60, 2);

        $actualHours = round($actualMinutes / 60, 2);
        $unscheduledActualHours = round($unscheduledMinutes / 60, 2);
        $scheduledActualHours = round($scheduledActualMinutes / 60, 2);

        // Completion rate: completed eligible occurrences / total eligible occurrences * 100
        $occurrenceCompletionRate = $scheduledCount > 0
            ? round(($completedCount / $scheduledCount) * 100, 1)
            : 0.0;

        // Planned-hour completion rate: completed planned hours / scheduled hours * 100
        $plannedHourCompletionRate = $scheduledHours > 0
            ? round(($completedPlannedHours / $scheduledHours) * 100, 1)
            : 0.0;

        return [
            'start_date' => $startDateStr,
            'end_date' => $endDateStr,
            'scheduled_hours' => $scheduledHours,
            'completed_planned_hours' => $completedPlannedHours,
            'remaining_scheduled_hours' => $remainingScheduledHours,
            'skipped_hours' => $skippedHours,
            'scheduled_occurrences_count' => $scheduledCount,
            'completed_occurrences_count' => $completedCount,
            'incomplete_occurrences_count' => $incompleteCount,
            'skipped_occurrences_count' => $skippedCount,
            'total_occurrences_count' => $totalCount,
            'occurrence_completion_rate' => $occurrenceCompletionRate,
            'planned_hour_completion_rate' => $plannedHourCompletionRate,
            'actual_hours' => $actualHours,
            'unscheduled_actual_hours' => $unscheduledActualHours,
            'scheduled_actual_hours' => $scheduledActualHours,
            'work_session_count' => $workSessionCount,
            'overdue_incomplete_count' => $overdueCount,
            'overdue_occurrences' => $overdueOccurrences,
        ];
    }

    /**
     * Get aggregated daily trends for a date range (up to 31 days).
     *
     * @return list<array<string, mixed>>
     */
    public function getDailyTrends(User $user, Carbon $startDate, Carbon $endDate, ?int $taskId = null): array
    {
        $tz = $user->timezone ?: 'UTC';
        $startDateStr = $startDate->toDateString();
        $endDateStr = $endDate->toDateString();

        // Group occurrences by scheduled_date
        $occurrencesByDate = ScheduleOccurrence::where('user_id', $user->id)
            ->where('status', '!=', 'cancelled')
            ->whereBetween('scheduled_date', [$startDateStr, $endDateStr])
            ->when($taskId, fn ($q) => $q->where('task_id', $taskId))
            ->selectRaw("
                scheduled_date,
                COALESCE(SUM(CASE WHEN status IN ('pending', 'completed') THEN duration_minutes ELSE 0 END), 0) as scheduled_minutes,
                COALESCE(SUM(CASE WHEN status = 'completed' THEN duration_minutes ELSE 0 END), 0) as completed_minutes,
                COUNT(CASE WHEN status IN ('pending', 'completed') THEN 1 END) as scheduled_count,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_count
            ")
            ->groupBy('scheduled_date')
            ->get()
            ->keyBy(fn ($item) => Carbon::parse($item->scheduled_date)->toDateString());

        // Group work sessions by date(started_at)
        $workByDate = WorkSession::where('user_id', $user->id)
            ->whereBetween('started_at', [
                $startDate->copy()->startOfDay()->toDateTimeString(),
                $endDate->copy()->endOfDay()->toDateTimeString(),
            ])
            ->when($taskId, fn ($q) => $q->where('task_id', $taskId))
            ->selectRaw("
                DATE(started_at) as work_date,
                COALESCE(SUM(duration_minutes), 0) as actual_minutes,
                COUNT(*) as session_count
            ")
            ->groupBy('work_date')
            ->get()
            ->keyBy('work_date');

        $trends = [];
        $curr = $startDate->copy();

        while ($curr->lte($endDate)) {
            $dateStr = $curr->toDateString();
            $occ = $occurrencesByDate->get($dateStr);
            $work = $workByDate->get($dateStr);

            $schedMins = (int) ($occ->scheduled_minutes ?? 0);
            $compMins = (int) ($occ->completed_minutes ?? 0);
            $actMins = (int) ($work->actual_minutes ?? 0);

            $schedCount = (int) ($occ->scheduled_count ?? 0);
            $compCount = (int) ($occ->completed_count ?? 0);

            $schedHours = round($schedMins / 60, 2);
            $compHours = round($compMins / 60, 2);
            $actHours = round($actMins / 60, 2);

            $trends[] = [
                'date' => $dateStr,
                'day_name' => $curr->format('D'),
                'day_num' => $curr->format('d'),
                'scheduled_hours' => $schedHours,
                'completed_hours' => $compHours,
                'actual_hours' => $actHours,
                'completion_rate' => $schedCount > 0 ? round(($compCount / $schedCount) * 100, 1) : 0.0,
            ];

            $curr->addDay();
        }

        return $trends;
    }

    /**
     * Get weekly trends for the past N weeks.
     *
     * @return list<array<string, mixed>>
     */
    public function getWeeklyTrends(User $user, int $weeks = 8, ?int $taskId = null): array
    {
        $tz = $user->timezone ?: 'UTC';
        $today = now($tz)->startOfDay();

        $trends = [];

        for ($i = $weeks - 1; $i >= 0; $i--) {
            $anchor = $today->copy()->subWeeks($i);
            $start = $anchor->copy()->startOfWeek();
            $end = $anchor->copy()->endOfWeek();

            $metrics = $this->getMetrics($user, $start, $end, $taskId);

            $trends[] = [
                'week_number' => (int) $start->format('W'),
                'year' => (int) $start->format('Y'),
                'label' => $start->format('M d') . ' – ' . $end->format('M d'),
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'scheduled_hours' => $metrics['scheduled_hours'],
                'completed_hours' => $metrics['completed_planned_hours'],
                'actual_hours' => $metrics['actual_hours'],
                'completion_rate' => $metrics['occurrence_completion_rate'],
                'planned_completion_rate' => $metrics['planned_hour_completion_rate'],
            ];
        }

        return $trends;
    }

    /**
     * Get monthly trends for the past N months.
     *
     * @return list<array<string, mixed>>
     */
    public function getMonthlyTrends(User $user, int $months = 6, ?int $taskId = null): array
    {
        $tz = $user->timezone ?: 'UTC';
        $today = now($tz)->startOfDay();

        $trends = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $anchor = $today->copy()->subMonths($i);
            $start = $anchor->copy()->startOfMonth();
            $end = $anchor->copy()->endOfMonth();

            $metrics = $this->getMetrics($user, $start, $end, $taskId);

            $trends[] = [
                'month_key' => $start->format('Y-m'),
                'label' => $start->format('M Y'),
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'scheduled_hours' => $metrics['scheduled_hours'],
                'completed_hours' => $metrics['completed_planned_hours'],
                'actual_hours' => $metrics['actual_hours'],
                'completion_rate' => $metrics['occurrence_completion_rate'],
                'planned_completion_rate' => $metrics['planned_hour_completion_rate'],
            ];
        }

        return $trends;
    }

    /**
     * Generate comprehensive overview report for a given period.
     *
     * @return array<string, mixed>
     */
    /**
     * Generate comprehensive overview report for a given period.
     *
     * @return array<string, mixed>
     */
    public function getOverviewReport(
        User $user,
        string $period = 'week',
        ?Carbon $customStart = null,
        ?Carbon $customEnd = null,
        ?int $taskId = null,
        ?Carbon $anchorDate = null
    ): array {
        $cacheKey = ReportCacheService::overviewCacheKey(
            $user,
            $period,
            $customStart,
            $customEnd,
            $taskId,
            $anchorDate
        );

        return Cache::remember($cacheKey, ReportCacheService::DEFAULT_TTL_SECONDS, function () use (
            $user,
            $period,
            $customStart,
            $customEnd,
            $taskId,
            $anchorDate
        ) {
            $bounds = $this->getPeriodBoundaries($user, $period, $customStart, $customEnd, $anchorDate);
            $metrics = $this->getMetrics($user, $bounds['start'], $bounds['end'], $taskId);

            // Daily trends for this period (if period length <= 31 days)
            $diffDays = $bounds['start']->diffInDays($bounds['end']) + 1;
            $dailyTrends = $diffDays <= 31
                ? $this->getDailyTrends($user, $bounds['start'], $bounds['end'], $taskId)
                : [];

            // Task breakdown list for all active tasks
            $tasks = $user->tasks()->active()->orderBy('title')->get(['id', 'title', 'description']);

            $tz = $user->timezone ?: 'UTC';
            $now = now($tz);
            $todayDate = $now->toDateString();
            $currentTimeStr = $now->format('H:i:s');
            $startDateStr = $bounds['start']->toDateString();
            $endDateStr = $bounds['end']->toDateString();

            // Single grouped SQL query for occurrence metrics per task
            $taskOccurrenceStats = ScheduleOccurrence::where('user_id', $user->id)
                ->where('status', '!=', 'cancelled')
                ->whereBetween('scheduled_date', [$startDateStr, $endDateStr])
                ->selectRaw("
                    task_id,
                    COALESCE(SUM(CASE WHEN status IN ('pending', 'completed') THEN duration_minutes ELSE 0 END), 0) as scheduled_minutes,
                    COALESCE(SUM(CASE WHEN status = 'completed' THEN duration_minutes ELSE 0 END), 0) as completed_minutes,
                    COALESCE(SUM(CASE WHEN status = 'pending' THEN duration_minutes ELSE 0 END), 0) as remaining_minutes,
                    COUNT(CASE WHEN status IN ('pending', 'completed') THEN 1 END) as scheduled_count,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_count,
                    COUNT(CASE WHEN status = 'pending' AND (scheduled_date < ? OR (scheduled_date = ? AND start_time <= ?)) THEN 1 END) as overdue_count
                ", [$todayDate, $todayDate, $currentTimeStr])
                ->groupBy('task_id')
                ->get()
                ->keyBy('task_id');

            // Single grouped SQL query for work session metrics per task
            $taskWorkStats = WorkSession::where('user_id', $user->id)
                ->whereBetween('started_at', [
                    $bounds['start']->copy()->startOfDay()->toDateTimeString(),
                    $bounds['end']->copy()->endOfDay()->toDateTimeString(),
                ])
                ->selectRaw("
                    task_id,
                    COALESCE(SUM(duration_minutes), 0) as actual_minutes
                ")
                ->groupBy('task_id')
                ->get()
                ->keyBy('task_id');

            $taskSummaries = [];
            foreach ($tasks as $task) {
                $occ = $taskOccurrenceStats->get($task->id);
                $work = $taskWorkStats->get($task->id);

                $schedMins = (int) ($occ->scheduled_minutes ?? 0);
                $compMins = (int) ($occ->completed_minutes ?? 0);
                $remMins = (int) ($occ->remaining_minutes ?? 0);
                $schedCount = (int) ($occ->scheduled_count ?? 0);
                $compCount = (int) ($occ->completed_count ?? 0);
                $overdueCount = (int) ($occ->overdue_count ?? 0);

                $actMins = (int) ($work->actual_minutes ?? 0);

                $scheduledHours = round($schedMins / 60, 2);
                $completedHours = round($compMins / 60, 2);
                $remainingHours = round($remMins / 60, 2);
                $actualHours = round($actMins / 60, 2);

                $occRate = $schedCount > 0 ? round(($compCount / $schedCount) * 100, 1) : 0.0;
                $hourRate = $scheduledHours > 0 ? round(($completedHours / $scheduledHours) * 100, 1) : 0.0;

                $taskSummaries[] = [
                    'id' => $task->id,
                    'title' => $task->title,
                    'scheduled_hours' => $scheduledHours,
                    'completed_planned_hours' => $completedHours,
                    'remaining_scheduled_hours' => $remainingHours,
                    'actual_hours' => $actualHours,
                    'occurrence_completion_rate' => $occRate,
                    'planned_hour_completion_rate' => $hourRate,
                    'scheduled_occurrences_count' => $schedCount,
                    'completed_occurrences_count' => $compCount,
                    'overdue_count' => $overdueCount,
                ];
            }

            return [
                'period' => $bounds['period'],
                'period_label' => $bounds['label'],
                'start_date' => $bounds['start']->toDateString(),
                'end_date' => $bounds['end']->toDateString(),
                'anchor_date' => $bounds['anchor_date'],
                'is_current' => $bounds['is_current'],
                'prev_date' => $bounds['prev_date'],
                'next_date' => $bounds['next_date'],
                'can_go_next' => $bounds['can_go_next'],
                'metrics' => $metrics,
                'daily_trends' => $dailyTrends,
                'task_summaries' => $taskSummaries,
                'tasks' => $tasks,
                'selected_task_id' => $taskId,
            ];
        });
    }

    /**
     * Generate full task-specific report with trends and chronological history.
     *
     * @return array<string, mixed>
     */
    public function getTaskReport(
        User $user,
        Task $task,
        string $period = 'month',
        ?Carbon $customStart = null,
        ?Carbon $customEnd = null,
        ?string $statusFilter = null,
        ?Carbon $anchorDate = null
    ): array {
        $cacheKey = ReportCacheService::taskReportCacheKey(
            $user,
            $task->id,
            $period,
            $customStart,
            $customEnd,
            $statusFilter,
            $anchorDate
        );

        return Cache::remember($cacheKey, ReportCacheService::DEFAULT_TTL_SECONDS, function () use (
            $user,
            $task,
            $period,
            $customStart,
            $customEnd,
            $statusFilter,
            $anchorDate
        ) {
            $bounds = $this->getPeriodBoundaries($user, $period, $customStart, $customEnd, $anchorDate);
            $metrics = $this->getMetrics($user, $bounds['start'], $bounds['end'], $task->id);

            // Daily trends (last 14 days or period)
            $dailyTrends = $this->getDailyTrends($user, $bounds['start'], $bounds['end'], $task->id);

            // Weekly trends (past 8 weeks)
            $weeklyTrends = $this->getWeeklyTrends($user, 8, $task->id);

            // Monthly trends (past 6 months)
            $monthlyTrends = $this->getMonthlyTrends($user, 6, $task->id);

            // Chronological scheduled occurrences history
            $occurrencesQuery = ScheduleOccurrence::where('user_id', $user->id)
                ->where('task_id', $task->id)
                ->where('status', '!=', 'cancelled')
                ->whereBetween('scheduled_date', [$bounds['start']->toDateString(), $bounds['end']->toDateString()]);

            if ($statusFilter && in_array($statusFilter, ['completed', 'pending', 'skipped'], true)) {
                $occurrencesQuery->where('status', $statusFilter);
            }

            $occurrencesHistory = $occurrencesQuery
                ->with(['recurringSchedule', 'workSessions'])
                ->orderByDesc('scheduled_date')
                ->orderByDesc('start_time')
                ->limit(100)
                ->get();

            // Chronological work sessions history (including unscheduled work)
            $workSessionsHistory = WorkSession::where('user_id', $user->id)
                ->where('task_id', $task->id)
                ->whereBetween('started_at', [
                    $bounds['start']->copy()->startOfDay()->toDateTimeString(),
                    $bounds['end']->copy()->endOfDay()->toDateTimeString(),
                ])
                ->with('occurrence')
                ->orderByDesc('started_at')
                ->limit(100)
                ->get();

            return [
                'task' => $task->only(['id', 'title', 'description', 'status']),
                'period' => $bounds['period'],
                'period_label' => $bounds['label'],
                'start_date' => $bounds['start']->toDateString(),
                'end_date' => $bounds['end']->toDateString(),
                'anchor_date' => $bounds['anchor_date'],
                'is_current' => $bounds['is_current'],
                'prev_date' => $bounds['prev_date'],
                'next_date' => $bounds['next_date'],
                'can_go_next' => $bounds['can_go_next'],
                'status_filter' => $statusFilter ?: 'all',
                'metrics' => $metrics,
                'daily_trends' => $dailyTrends,
                'weekly_trends' => $weeklyTrends,
                'monthly_trends' => $monthlyTrends,
                'occurrences_history' => $occurrencesHistory,
                'work_sessions_history' => $workSessionsHistory,
            ];
        });
    }
}
