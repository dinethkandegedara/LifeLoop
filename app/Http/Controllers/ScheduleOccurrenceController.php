<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateOccurrenceRequest;
use App\Models\ScheduleOccurrence;
use App\Services\Scheduling\ScheduleManager;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ScheduleOccurrenceController extends Controller
{
    use AuthorizesRequests;

    /**
     * List schedule occurrences for a date range.
     */
    public function index(Request $request, ScheduleManager $manager): JsonResponse
    {
        $user = $request->user();
        $tz = $user->timezone ?: 'UTC';

        $startDate = $request->input('start_date')
            ? Carbon::parse($request->input('start_date'), $tz)
            : now($tz)->startOfWeek();

        $endDate = $request->input('end_date')
            ? Carbon::parse($request->input('end_date'), $tz)
            : now($tz)->endOfWeek();

        $taskId = $request->filled('task_id') ? (int) $request->input('task_id') : null;

        $occurrences = $manager->getOccurrencesForRange($user, $startDate, $endDate, $taskId);

        return response()->json([
            'occurrences' => $occurrences,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
        ]);
    }

    /**
     * Edit a single occurrence, recording the exception and original schedule details.
     */
    public function update(UpdateOccurrenceRequest $request, ScheduleOccurrence $occurrence, ScheduleManager $manager): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $occurrence);

        $updated = $manager->editSingleOccurrence($occurrence, $request->validated());

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Occurrence updated successfully.',
                'occurrence' => $updated,
            ]);
        }

        return back()->with('success', 'Occurrence updated successfully.');
    }

    /**
     * Mark an occurrence as completed.
     */
    public function complete(Request $request, ScheduleOccurrence $occurrence, ScheduleManager $manager): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $occurrence);

        $completed = $manager->completeOccurrence($occurrence);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Occurrence marked as completed.',
                'occurrence' => $completed,
            ]);
        }

        return back()->with('success', 'Occurrence marked as completed.');
    }

    /**
     * Mark an occurrence as skipped.
     */
    public function skip(Request $request, ScheduleOccurrence $occurrence, ScheduleManager $manager): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $occurrence);

        $skipped = $manager->skipOccurrence($occurrence);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Occurrence marked as skipped.',
                'occurrence' => $skipped,
            ]);
        }

        return back()->with('success', 'Occurrence marked as skipped.');
    }

    /**
     * Reopen a completed or skipped occurrence back to pending.
     */
    public function reopen(Request $request, ScheduleOccurrence $occurrence): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $occurrence);

        $occurrence->update([
            'status' => 'pending',
            'completed_at' => null,
        ]);

        \App\Services\Reporting\ReportCacheService::invalidateUser($occurrence->user_id);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Occurrence reopened successfully.',
                'occurrence' => $occurrence->fresh(['task', 'workSessions']),
            ]);
        }

        return back()->with('success', 'Occurrence reopened.');
    }
}
