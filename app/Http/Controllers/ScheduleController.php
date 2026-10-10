<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRecurringScheduleRequest;
use App\Http\Requests\UpdateRecurringScheduleRequest;
use App\Models\RecurringSchedule;
use App\Models\Task;
use App\Services\Scheduling\ScheduleManager;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ScheduleController extends Controller
{
    use AuthorizesRequests;

    /**
     * Synchronize all recurring schedule rules for a task.
     */
    public function sync(Request $request, Task $task, ScheduleManager $manager): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $task);

        $validated = $request->validate([
            'timezone' => ['nullable', 'string', 'timezone:all'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'rules' => ['present', 'array'],
            'rules.*.type' => ['required', 'string', Rule::in(['one_time', 'daily', 'weekly', 'monthly_date', 'monthly_day'])],
            'rules.*.start_date' => ['nullable', 'date'],
            'rules.*.end_date' => ['nullable', 'date'],
            'rules.*.start_time' => ['required'],
            'rules.*.duration_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'rules.*.weekdays' => ['nullable', 'array'],
            'rules.*.weekdays.*' => ['integer', 'between:1,7'],
            'rules.*.month_day' => ['nullable', 'integer', 'between:1,31'],
            'rules.*.month_week' => ['nullable', 'integer', Rule::in([1, 2, 3, 4, -1])],
            'rules.*.month_weekday' => ['nullable', 'integer', 'between:1,7'],
        ]);

        $effectiveDate = $request->input('effective_date') ? Carbon::parse($request->input('effective_date')) : null;
        $manager->syncTaskSchedules($task, $validated, $effectiveDate);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Task schedule synchronized successfully.',
            ]);
        }

        return back()->with('success', 'Task schedule synchronized successfully.');
    }

    /**
     * Store a new recurring schedule for a task.
     */
    public function store(StoreRecurringScheduleRequest $request, Task $task, ScheduleManager $manager): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $task);

        $schedule = $manager->createSchedule($task, $request->validated());

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Schedule created successfully.',
                'schedule' => $schedule,
            ], 201);
        }

        return back()->with('success', 'Schedule created successfully.');
    }

    /**
     * Update an entire recurring schedule rule applying changes to future applicable occurrences.
     */
    public function update(UpdateRecurringScheduleRequest $request, RecurringSchedule $schedule, ScheduleManager $manager): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $schedule);

        $data = $request->validated();
        $effectiveDate = isset($data['effective_date']) ? Carbon::parse($data['effective_date']) : null;

        $updatedSchedule = $manager->updateScheduleRule($schedule, $data, $effectiveDate);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Schedule updated successfully.',
                'schedule' => $updatedSchedule,
            ]);
        }

        return back()->with('success', 'Schedule rule updated successfully.');
    }

    /**
     * Deactivate a schedule and supersede future unworked occurrences.
     */
    public function destroy(Request $request, RecurringSchedule $schedule, ScheduleManager $manager): RedirectResponse|JsonResponse
    {
        $this->authorize('delete', $schedule);

        $effectiveDate = $request->input('effective_date') ? Carbon::parse($request->input('effective_date')) : null;
        $manager->deactivateSchedule($schedule, $effectiveDate);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Schedule deactivated successfully.',
            ]);
        }

        return back()->with('success', 'Schedule deactivated successfully.');
    }
}
