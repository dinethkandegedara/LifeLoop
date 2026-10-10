<?php

namespace App\Http\Controllers;

use App\Models\ScheduleOccurrence;
use App\Models\WorkSession;
use App\Services\Scheduling\ScheduleManager;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TodayController extends Controller
{
    /**
     * Render the Calendar / Today focus view with real schedule occurrences and work sessions.
     * Supports Today, Week, and Month views with user timezone alignment and efficient range loading.
     */
    public function __invoke(
        Request $request,
        ScheduleManager $manager,
        ?\App\Services\Habits\StreakService $streakService = null
    ): Response {
        $streakService = $streakService ?: app(\App\Services\Habits\StreakService::class);
        $user = $request->user();
        $tz = $user->timezone ?: 'UTC';
        $today = now($tz)->startOfDay();

        // 1. Resolve active view ('today', 'week', 'month')
        $view = $request->input('view', 'today');
        if (! in_array($view, ['today', 'week', 'month'], true)) {
            $view = 'today';
        }

        // 2. Resolve selected anchor date in user timezone
        $selectedDate = $request->filled('date')
            ? Carbon::parse($request->input('date'), $tz)->startOfDay()
            : $today;

        // 3. Compute range boundaries based on active view
        if ($view === 'week') {
            // Monday to Sunday of the selected week in user timezone
            $rangeStart = $selectedDate->copy()->startOfWeek();
            $rangeEnd = $selectedDate->copy()->endOfWeek();
        } elseif ($view === 'month') {
            // Full calendar grid: Monday of the first visible week to Sunday of the last visible week
            $monthStart = $selectedDate->copy()->startOfMonth();
            $monthEnd = $selectedDate->copy()->endOfMonth();
            $rangeStart = $monthStart->copy()->startOfWeek();
            $rangeEnd = $monthEnd->copy()->endOfWeek();
        } else {
            // Today view: single day
            $rangeStart = $selectedDate;
            $rangeEnd = $selectedDate;
        }

        // 4. User's active tasks for work logging options
        $tasks = $user->tasks()
            ->active()
            ->orderBy('title')
            ->get(['id', 'title', 'description', 'status']);

        // 5. Load occurrences within the active date range
        $occurrences = $manager->getOccurrencesForRange($user, $rangeStart, $rangeEnd);

        // 6. Load work sessions within the active range
        $rangeWorkSessions = WorkSession::where('user_id', $user->id)
            ->whereBetween('started_at', [
                $rangeStart->copy()->startOfDay()->toDateTimeString(),
                $rangeEnd->copy()->endOfDay()->toDateTimeString(),
            ])
            ->with(['task:id,title', 'occurrence'])
            ->orderByDesc('started_at')
            ->get();

        // 7. Work sessions recorded on the selected date (for backwards compatibility & daily stats)
        $todayWorkSessions = $rangeWorkSessions->filter(function ($ws) use ($selectedDate, $tz) {
            return Carbon::parse($ws->started_at, $tz)->toDateString() === $selectedDate->toDateString();
        })->values();

        // 8. Recent recorded work sessions (for audit history log)
        $recentWorkSessions = WorkSession::where('user_id', $user->id)
            ->with(['task:id,title', 'occurrence'])
            ->orderByDesc('started_at')
            ->limit(50)
            ->get();

        // 9. Unresolved Overdue Occurrences prior to today (pending occurrences in past 30 days)
        $overdueOccurrences = ScheduleOccurrence::where('user_id', $user->id)
            ->where('status', 'pending')
            ->where('scheduled_date', '<', $today->toDateString())
            ->where('scheduled_date', '>=', $today->copy()->subDays(30)->toDateString())
            ->with(['task:id,title', 'recurringSchedule', 'workSessions'])
            ->orderBy('scheduled_date')
            ->orderBy('start_time')
            ->limit(20)
            ->get();

        return Inertia::render('Today', [
            'tasks' => $tasks,
            'occurrences' => $occurrences,
            'todayWorkSessions' => $todayWorkSessions,
            'recentWorkSessions' => $recentWorkSessions,
            'todayDate' => $today->toDateString(),
            'selectedDate' => $selectedDate->toDateString(),
            'userTimezone' => $tz,
            'currentView' => $view,
            'rangeStart' => $rangeStart->toDateString(),
            'rangeEnd' => $rangeEnd->toDateString(),
            'rangeWorkSessions' => $rangeWorkSessions,
            'overdueOccurrences' => $overdueOccurrences,
            'streak' => $streakService->getStreakData($user),
        ]);
    }
}

