<?php

namespace App\Http\Controllers;

use App\Models\WorkSession;
use App\Services\Scheduling\ScheduleManager;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TodayController extends Controller
{
    /**
     * Render the Today focus view with real schedule occurrences and work sessions.
     */
    public function __invoke(Request $request, ScheduleManager $manager): Response
    {
        $user = $request->user();
        $tz = $user->timezone ?: 'UTC';
        $today = now($tz)->startOfDay();
        $selectedDate = $request->filled('date')
            ? Carbon::parse($request->input('date'), $tz)->startOfDay()
            : $today;

        // 1. User's active tasks
        $tasks = $user->tasks()
            ->active()
            ->orderBy('title')
            ->get(['id', 'title', 'description', 'status']);

        // 2. Selected date's schedule occurrences
        $occurrences = $manager->getOccurrencesForRange($user, $selectedDate, $selectedDate);

        // 3. Selected date's recorded work sessions
        $todayWorkSessions = WorkSession::where('user_id', $user->id)
            ->whereDate('started_at', $selectedDate->toDateString())
            ->with(['task:id,title', 'occurrence'])
            ->orderByDesc('started_at')
            ->get();

        // 4. Recent recorded work sessions (audit history)
        $recentWorkSessions = WorkSession::where('user_id', $user->id)
            ->with(['task:id,title', 'occurrence'])
            ->orderByDesc('started_at')
            ->limit(50)
            ->get();

        return Inertia::render('Today', [
            'tasks' => $tasks,
            'occurrences' => $occurrences,
            'todayWorkSessions' => $todayWorkSessions,
            'recentWorkSessions' => $recentWorkSessions,
            'todayDate' => $today->toDateString(),
            'selectedDate' => $selectedDate->toDateString(),
            'userTimezone' => $tz,
        ]);
    }
}
