<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecurringSchedule extends Model
{
    /** @use HasFactory<\Database\Factories\RecurringScheduleFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'task_id',
        'type',
        'start_date',
        'end_date',
        'start_time',
        'duration_minutes',
        'timezone',
        'interval',
        'weekdays',
        'month_day',
        'month_week',
        'month_weekday',
        'selected_dates',
        'exclusions',
        'is_active',
        'version',
        'last_generated_until',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'duration_minutes' => 'integer',
            'interval' => 'integer',
            'weekdays' => 'array',
            'month_day' => 'integer',
            'month_week' => 'integer',
            'month_weekday' => 'integer',
            'selected_dates' => 'array',
            'exclusions' => 'array',
            'is_active' => 'boolean',
            'version' => 'integer',
            'last_generated_until' => 'date:Y-m-d',
        ];
    }

    /**
     * User who owns this schedule.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Task this schedule plans for.
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * All occurrences generated or attached to this schedule.
     */
    public function occurrences(): HasMany
    {
        return $this->hasMany(ScheduleOccurrence::class);
    }

    /**
     * Check if a specific date string is excluded.
     */
    public function isDateExcluded(string $dateString): bool
    {
        $exclusions = $this->exclusions ?? [];

        return in_array($dateString, $exclusions, true);
    }

    /**
     * Check if date is before start date or after end date.
     */
    public function isDateWithinBounds(Carbon $date): bool
    {
        $dateStr = $date->toDateString();
        $startDateStr = $this->start_date ? Carbon::parse($this->start_date)->toDateString() : null;
        $endDateStr = $this->end_date ? Carbon::parse($this->end_date)->toDateString() : null;

        if ($startDateStr && $dateStr < $startDateStr) {
            return false;
        }

        if ($endDateStr && $dateStr > $endDateStr) {
            return false;
        }

        return true;
    }
}
