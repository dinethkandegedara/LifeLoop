<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Task;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the user's tasks with search and filtering.
     */
    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $status = $request->query('status', 'active'); // 'active', 'archived', 'all'

        $query = $request->user()->tasks()
            ->with([
                'activeRecurringSchedules',
                'scheduleOccurrences' => fn ($q) => $q->where('status', '!=', 'cancelled')
                    ->orderBy('scheduled_date', 'asc')
                    ->orderBy('start_time', 'asc')
                    ->limit(20),
            ])
            ->search($search);

        if ($status === 'active') {
            $query->active();
        } elseif ($status === 'archived') {
            $query->archived();
        }

        $tasks = $query->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Task $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'status' => $task->status,
                'is_archived' => $task->isArchived(),
                'has_history' => $task->hasHistory(),
                'created_at' => $task->created_at?->toIso8601String(),
                'created_at_human' => $task->created_at?->format('M j, Y'),
                'archived_at' => $task->archived_at?->toIso8601String(),
                'schedules' => $task->activeRecurringSchedules->map(fn ($s) => [
                    'id' => $s->id,
                    'type' => $s->type,
                    'start_date' => $s->start_date?->toDateString(),
                    'end_date' => $s->end_date?->toDateString(),
                    'start_time' => $s->start_time,
                    'duration_minutes' => $s->duration_minutes,
                    'interval' => $s->interval,
                    'weekdays' => $s->weekdays,
                    'is_active' => $s->is_active,
                ]),
                'occurrences' => $task->scheduleOccurrences->map(fn ($o) => [
                    'id' => $o->id,
                    'recurring_schedule_id' => $o->recurring_schedule_id,
                    'scheduled_date' => $o->scheduled_date?->toDateString(),
                    'start_time' => $o->start_time,
                    'duration_minutes' => $o->duration_minutes,
                    'status' => $o->status,
                    'is_exception' => $o->is_exception,
                    'completed_at' => $o->completed_at?->toIso8601String(),
                    'notes' => $o->notes,
                ]),
            ]);

        $counts = [
            'active' => $request->user()->tasks()->active()->count(),
            'archived' => $request->user()->tasks()->archived()->count(),
            'total' => $request->user()->tasks()->count(),
        ];

        return Inertia::render('Tasks/Index', [
            'tasks' => $tasks,
            'filters' => [
                'search' => $search ?? '',
                'status' => $status,
            ],
            'counts' => $counts,
            'userTimezone' => $request->user()->timezone ?: 'UTC',
        ]);
    }

    /**
     * Store a newly created task for the authenticated user.
     */
    public function store(StoreTaskRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $status = $validated['status'] ?? 'active';

        $request->user()->tasks()->create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'status' => $status,
            'archived_at' => $status === 'archived' ? now() : null,
            'has_history' => false,
        ]);

        return redirect()->back()->with('success', 'Task created successfully.');
    }

    /**
     * Update the specified task.
     */
    public function update(UpdateTaskRequest $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $validated = $request->validated();

        $task->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
        ]);

        if (isset($validated['status'])) {
            if ($validated['status'] === 'archived') {
                $task->archive();
            } else {
                $task->unarchive();
            }
        }

        return redirect()->back()->with('success', 'Task updated successfully.');
    }

    /**
     * Archive the specified task.
     */
    public function archive(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('archive', $task);

        $task->archive();

        return redirect()->back()->with('success', 'Task archived successfully.');
    }

    /**
     * Restore an archived task to active.
     */
    public function unarchive(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('unarchive', $task);

        $task->unarchive();

        return redirect()->back()->with('success', 'Task restored to active.');
    }

    /**
     * Remove the specified task from storage.
     * Prevents permanent deletion if history exists.
     */
    public function destroy(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('delete', $task);

        if ($task->hasHistory()) {
            return redirect()->back()
                ->withErrors([
                    'delete' => 'Cannot permanently delete task because history exists. Archive the task instead to preserve records.',
                ])
                ->with('error', 'Cannot permanently delete task because history exists. Archive the task instead to preserve records.');
        }

        $task->delete();

        return redirect()->back()->with('success', 'Task permanently deleted.');
    }
}
