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

    /**
     * Update an existing work session.
     */
    public function update(\App\Http\Requests\UpdateWorkSessionRequest $request, WorkSession $workSession): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $workSession);

        if ($request->filled('task_id')) {
            $task = Task::where('user_id', $request->user()->id)->findOrFail($request->input('task_id'));
            $workSession->task_id = $task->id;
        }

        if ($request->has('schedule_occurrence_id')) {
            $occurrenceId = $request->input('schedule_occurrence_id');
            if ($occurrenceId) {
                $occ = ScheduleOccurrence::where('user_id', $request->user()->id)->findOrFail($occurrenceId);
                $workSession->schedule_occurrence_id = $occ->id;
            } else {
                $workSession->schedule_occurrence_id = null;
            }
        }

        if ($request->filled('started_at')) {
            $workSession->started_at = $request->input('started_at');
        }

        if ($request->filled('duration_minutes')) {
            $workSession->duration_minutes = (int) $request->input('duration_minutes');
        }

        if ($request->has('ended_at')) {
            $workSession->ended_at = $request->input('ended_at');
        }

        if ($request->has('notes')) {
            $workSession->notes = $request->input('notes');
        }

        $workSession->save();

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Work session updated successfully.',
                'work_session' => $workSession->fresh(['task:id,title', 'occurrence']),
            ]);
        }

        return back()->with('success', 'Work session updated successfully.');
    }

    /**
     * Delete a work session.
     */
    public function destroy(Request $request, WorkSession $workSession): RedirectResponse|JsonResponse
    {
        $this->authorize('delete', $workSession);

        $workSession->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Work session deleted successfully.',
            ]);
        }

        return back()->with('success', 'Work session deleted successfully.');
    }
}
