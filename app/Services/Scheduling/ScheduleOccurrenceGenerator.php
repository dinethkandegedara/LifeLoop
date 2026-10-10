<?php

namespace App\Services\Scheduling;

use App\Models\RecurringSchedule;
use App\Models\ScheduleOccurrence;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ScheduleOccurrenceGenerator
{
    public function __construct(
        protected RecurrenceRuleEvaluator $evaluator
    ) {}

    /**
     * Generate occurrences for a recurring schedule over a bounded window idempotently.
     *
     * @return Collection<int, ScheduleOccurrence>
     */
    public function generate(RecurringSchedule $schedule, ?Carbon $until = null, ?Carbon $from = null): Collection
    {
        return DB::transaction(function () use ($schedule, $until, $from) {
            // Pessimistic lock on schedule to prevent concurrent generation races
            /** @var RecurringSchedule $lockedSchedule */
            $lockedSchedule = RecurringSchedule::where('id', $schedule->id)->lockForUpdate()->firstOrFail();

            if (! $lockedSchedule->is_active) {
                return new Collection();
            }

            $tz = $lockedSchedule->timezone ?: 'UTC';
            $startDate = Carbon::parse($lockedSchedule->start_date, $tz)->startOfDay();
            $endDate = $lockedSchedule->end_date ? Carbon::parse($lockedSchedule->end_date, $tz)->startOfDay() : null;

            // Bounded window calculation
            $windowStart = $from
                ? $from->copy()->setTimezone($tz)->startOfDay()
                : $startDate->copy();

            if ($windowStart->lessThan($startDate)) {
                $windowStart = $startDate->copy();
            }

            $defaultHorizonDays = 60;
            $windowEnd = $until
                ? $until->copy()->setTimezone($tz)->startOfDay()
                : $windowStart->copy()->addDays($defaultHorizonDays);

            if ($endDate && $windowEnd->greaterThan($endDate)) {
                $windowEnd = $endDate->copy();
            }

            if ($windowStart->greaterThan($windowEnd)) {
                return new Collection();
            }

            // Evaluate dates via recurrence engine
            $targetDates = $this->evaluator->evaluate($lockedSchedule, $windowStart, $windowEnd);

            if (empty($targetDates)) {
                $lockedSchedule->update([
                    'last_generated_until' => max($lockedSchedule->last_generated_until?->toDateString(), $windowEnd->toDateString()),
                ]);

                return new Collection();
            }

            // Fetch all active existing occurrences for this schedule in the window to guarantee idempotency
            $existingOccurrences = ScheduleOccurrence::where('recurring_schedule_id', $lockedSchedule->id)
                ->whereBetween('scheduled_date', [$windowStart->toDateString(), $windowEnd->toDateString()])
                ->where('status', '!=', 'cancelled')
                ->get()
                ->keyBy(fn (ScheduleOccurrence $occ) => $occ->scheduled_date->toDateString());

            $newOccurrences = [];
            $startTime = Carbon::parse($lockedSchedule->start_time)->format('H:i:s');
            $durationMinutes = max(1, (int) $lockedSchedule->duration_minutes);

            foreach ($targetDates as $dateInstance) {
                $dateStr = $dateInstance->toDateString();

                // If occurrence already exists on this date, preserve it without duplicating
                if ($existingOccurrences->has($dateStr)) {
                    continue;
                }

                // Check exclusions
                if ($lockedSchedule->isDateExcluded($dateStr)) {
                    continue;
                }

                // Calculate timezone-aware start and end times, accurately accounting for DST transitions
                $localStart = Carbon::createFromFormat('Y-m-d H:i:s', "{$dateStr} {$startTime}", $tz);
                $localEnd = $localStart->copy()->addMinutes($durationMinutes);

                $utcStartAt = $localStart->copy()->setTimezone('UTC');
                $utcEndAt = $localEnd->copy()->setTimezone('UTC');

                $newOccurrences[] = [
                    'user_id' => $lockedSchedule->user_id,
                    'task_id' => $lockedSchedule->task_id,
                    'recurring_schedule_id' => $lockedSchedule->id,
                    'scheduled_date' => $dateStr,
                    'start_time' => $startTime,
                    'end_time' => $localEnd->format('H:i:s'),
                    'duration_minutes' => $durationMinutes,
                    'timezone' => $tz,
                    'utc_start_at' => $utcStartAt->toDateTimeString(),
                    'utc_end_at' => $utcEndAt->toDateTimeString(),
                    'status' => 'pending',
                    'is_exception' => false,
                    'original_scheduled_date' => null,
                    'original_start_time' => null,
                    'original_duration_minutes' => null,
                    'notes' => null,
                    'superseded_at' => null,
                    'completed_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (! empty($newOccurrences)) {
                ScheduleOccurrence::insert($newOccurrences);
            }

            $maxGeneratedDate = max($lockedSchedule->last_generated_until?->toDateString(), $windowEnd->toDateString());
            $lockedSchedule->update(['last_generated_until' => $maxGeneratedDate]);

            return ScheduleOccurrence::where('recurring_schedule_id', $lockedSchedule->id)
                ->whereBetween('scheduled_date', [$windowStart->toDateString(), $windowEnd->toDateString()])
                ->where('status', '!=', 'cancelled')
                ->orderBy('scheduled_date')
                ->orderBy('start_time')
                ->get();
        });
    }
}
