<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkSessionRequest;
use App\Models\ScheduleOccurrence;
use App\Models\Task;
use App\Models\WorkSession;
use App\Services\Scheduling\ScheduleManager;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkSessionController extends Controller
{
    use AuthorizesRequests;

    /**
     * List work sessions recorded for the user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $sessions = WorkSession::where('user_id', $user->id)
            ->when($request->filled('task_id'), fn ($q) => $q->where('task_id', $request->input('task_id')))
            ->when($request->filled('from'), fn ($q) => $q->where('started_at', '>=', $request->input('from')))
            ->when($request->filled('until'), fn ($q) => $q->where('started_at', '<=', $request->input('until')))
            ->with(['task', 'occurrence'])
            ->orderByDesc('started_at')
            ->get();

        return response()->json([
            'work_sessions' => $sessions,
        ]);
    }

    /**
     * Record an actual work session.
     */
    public function store(StoreWorkSessionRequest $request, ScheduleManager $manager): RedirectResponse|JsonResponse
    {
        $task = Task::findOrFail($request->input('task_id'));
        $this->authorize('update', $task);

        $occurrence = null;
        if ($request->filled('schedule_occurrence_id')) {
            $occurrence = ScheduleOccurrence::where('user_id', $request->user()->id)
                ->findOrFail($request->input('schedule_occurrence_id'));
        }

        $session = $manager->recordWorkSession($task, $request->validated(), $occurrence);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Work session recorded successfully.',
                'work_session' => $session->load(['task', 'occurrence']),
            ], 201);
        }

        return back()->with('success', 'Work session recorded successfully.');
    }
}
