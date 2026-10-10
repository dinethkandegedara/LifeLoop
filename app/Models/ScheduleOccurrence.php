<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduleOccurrence extends Model
{
    /** @use HasFactory<\Database\Factories\ScheduleOccurrenceFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'task_id',
        'recurring_schedule_id',
        'scheduled_date',
        'start_time',
        'duration_minutes',
        'end_time',
        'timezone',
        'utc_start_at',
        'utc_end_at',
        'status',
        'is_exception',
        'original_scheduled_date',
        'original_start_time',
        'original_duration_minutes',
        'notes',
        'superseded_at',
        'completed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date:Y-m-d',
            'duration_minutes' => 'integer',
            'is_exception' => 'boolean',
            'original_scheduled_date' => 'date:Y-m-d',
            'original_duration_minutes' => 'integer',
            'superseded_at' => 'datetime',
            'completed_at' => 'datetime',
            'utc_start_at' => 'datetime',
            'utc_end_at' => 'datetime',
        ];
    }

    /**
     * User who owns the occurrence.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Task this planned occurrence is for.
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Parent recurring schedule, if any.
     */
    public function recurringSchedule(): BelongsTo
    {
        return $this->belongsTo(RecurringSchedule::class);
    }

    /**
     * Actual work sessions linked to this planned occurrence.
     */
    public function workSessions(): HasMany
    {
        return $this->hasMany(WorkSession::class);
    }

    /**
     * Check if this occurrence has recorded actual work.
     */
    public function hasRecordedWork(): bool
    {
        return $this->workSessions()->exists();
    }

    /**
     * Check if this occurrence is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if this occurrence was superseded/cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Mark this occurrence as completed.
     */
    public function markCompleted(?Carbon $timestamp = null): bool
    {
        $this->status = 'completed';
        $this->completed_at = $timestamp ?? now();

        return $this->save();
    }

    /**
     * Mark this occurrence as skipped.
     */
    public function markSkipped(): bool
    {
        $this->status = 'skipped';

        return $this->save();
    }

    /**
     * Cancel / supersede this occurrence preserving audit history.
     */
    public function supersede(): bool
    {
        $this->status = 'cancelled';
        $this->superseded_at = now();

        return $this->save();
    }

    /**
     * Scope query to non-cancelled occurrences.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', '!=', 'cancelled');
    }

    /**
     * Scope query to a specific date range.
     */
    public function scopeForRange(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('scheduled_date', [$startDate, $endDate]);
    }
}
