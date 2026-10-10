<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Services\Reporting\ScheduleReportingService;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected ScheduleReportingService $reportingService
    ) {}

    /**
     * Display centralized reports overview across Today, Week, Month, or Custom range.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $tz = $user->timezone ?: 'UTC';

        $period = $request->input('period', 'week');
        if (! in_array($period, ['today', 'week', 'month', 'custom'], true)) {
            $period = 'week';
        }

        $customStart = $request->filled('from')
            ? Carbon::parse($request->input('from'), $tz)
            : null;

        $customEnd = $request->filled('to')
            ? Carbon::parse($request->input('to'), $tz)
            : null;

        $taskId = $request->filled('task_id') ? (int) $request->input('task_id') : null;

        $anchorDate = $request->filled('date')
            ? Carbon::parse($request->input('date'), $tz)
            : null;

        $reportData = $this->reportingService->getOverviewReport(
            $user,
            $period,
            $customStart,
            $customEnd,
            $taskId,
            $anchorDate
        );

        return Inertia::render('Reports/Index', $reportData);
    }

    /**
     * Display dedicated per-task report with trends and chronological history.
     */
    public function taskReport(Request $request, Task $task): Response
    {
        $this->authorize('view', $task);

        $user = $request->user();
        $tz = $user->timezone ?: 'UTC';

        $period = $request->input('period', 'month');
        if (! in_array($period, ['today', 'week', 'month', 'all', 'custom'], true)) {
            $period = 'month';
        }

        $customStart = $request->filled('from')
            ? Carbon::parse($request->input('from'), $tz)
            : null;

        $customEnd = $request->filled('to')
            ? Carbon::parse($request->input('to'), $tz)
            : null;

        $statusFilter = $request->input('status');

        $anchorDate = $request->filled('date')
            ? Carbon::parse($request->input('date'), $tz)
            : null;

        $taskReportData = $this->reportingService->getTaskReport(
            $user,
            $task,
            $period,
            $customStart,
            $customEnd,
            $statusFilter,
            $anchorDate
        );

        return Inertia::render('Reports/TaskReport', $taskReportData);
    }
}
