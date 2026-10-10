<?php

namespace App\Services\Scheduling;

use App\Models\RecurringSchedule;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class RecurrenceRuleEvaluator
{
    /**
     * Evaluate recurrence rule and return matching date instances in schedule timezone.
     *
     * @return list<Carbon>
     */
    public function evaluate(RecurringSchedule $schedule, Carbon $windowStart, Carbon $windowEnd): array
    {
        $tz = $schedule->timezone ?: 'UTC';
        $startDate = Carbon::parse($schedule->start_date, $tz)->startOfDay();
        $endDate = $schedule->end_date ? Carbon::parse($schedule->end_date, $tz)->startOfDay() : null;

        $effectiveStart = $startDate->greaterThan($windowStart) ? $startDate->copy() : $windowStart->copy()->startOfDay();
        $effectiveEnd = $endDate && $endDate->lessThan($windowEnd) ? $endDate->copy() : $windowEnd->copy()->startOfDay();

        if ($effectiveStart->greaterThan($effectiveEnd)) {
            return [];
        }

        $dates = match ($schedule->type) {
            'one_time' => $this->evaluateOneTime($startDate, $effectiveStart, $effectiveEnd),
            'daily' => $this->evaluateDaily($schedule, $startDate, $effectiveStart, $effectiveEnd),
            'weekly' => $this->evaluateWeekly($schedule, $startDate, $effectiveStart, $effectiveEnd),
            'monthly_date' => $this->evaluateMonthlyDate($schedule, $startDate, $effectiveStart, $effectiveEnd),
            'monthly_day' => $this->evaluateMonthlyDay($schedule, $startDate, $effectiveStart, $effectiveEnd),
            'custom_dates' => $this->evaluateCustomDates($schedule, $effectiveStart, $effectiveEnd),
            default => [],
        };

        // Filter out exclusions and outside bounds
        $exclusions = $schedule->exclusions ?? [];

        return array_values(array_filter($dates, function (Carbon $d) use ($exclusions, $startDate, $endDate, $effectiveStart, $effectiveEnd) {
            $dateStr = $d->toDateString();

            if (in_array($dateStr, $exclusions, true)) {
                return false;
            }

            if ($d->lessThan($startDate)) {
                return false;
            }

            if ($endDate && $d->greaterThan($endDate)) {
                return false;
            }

            return $d->betweenIncluded($effectiveStart, $effectiveEnd);
        }));
    }

    /**
     * One-time scheduled session: exactly on start_date.
     *
     * @return list<Carbon>
     */
    protected function evaluateOneTime(Carbon $startDate, Carbon $windowStart, Carbon $windowEnd): array
    {
        if ($startDate->betweenIncluded($windowStart, $windowEnd)) {
            return [$startDate->copy()];
        }

        return [];
    }

    /**
     * Daily recurrence: every N days from start_date.
     *
     * @return list<Carbon>
     */
    protected function evaluateDaily(RecurringSchedule $schedule, Carbon $startDate, Carbon $windowStart, Carbon $windowEnd): array
    {
        $interval = max(1, (int) ($schedule->interval ?: 1));
        $dates = [];

        // Calculate offset to jump near windowStart for efficiency
        $daysDiff = $startDate->diffInDays($windowStart, false);
        if ($daysDiff > 0) {
            $steps = (int) ceil($daysDiff / $interval);
            $cursor = $startDate->copy()->addDays($steps * $interval);
        } else {
            $cursor = $startDate->copy();
        }

        while ($cursor->lessThanOrEqualTo($windowEnd)) {
            if ($cursor->greaterThanOrEqualTo($windowStart)) {
                $dates[] = $cursor->copy();
            }
            $cursor->addDays($interval);
        }

        return $dates;
    }

    /**
     * Weekly recurrence on selected weekdays every N weeks.
     * Weekdays are 1 (Monday) through 7 (Sunday).
     *
     * @return list<Carbon>
     */
    protected function evaluateWeekly(RecurringSchedule $schedule, Carbon $startDate, Carbon $windowStart, Carbon $windowEnd): array
    {
        $interval = max(1, (int) ($schedule->interval ?: 1));
        $weekdays = $schedule->weekdays;

        // If no weekdays specified, default to start_date's day of week (ISO 1..7)
        if (empty($weekdays) || !is_array($weekdays)) {
            $weekdays = [$startDate->dayOfWeekIso];
        }

        // Map and sort weekdays (1=Mon..7=Sun)
        $weekdays = array_values(array_unique(array_map('intval', $weekdays)));
        sort($weekdays);

        $dates = [];
        $anchorWeek = $startDate->copy()->startOfWeek(CarbonInterface::MONDAY);
        $startWeek = $windowStart->copy()->startOfWeek(CarbonInterface::MONDAY);

        // Determine first candidate week
        $weeksDiff = $anchorWeek->diffInWeeks($startWeek, false);
        if ($weeksDiff > 0) {
            $steps = (int) floor($weeksDiff / $interval);
            $currentWeek = $anchorWeek->copy()->addWeeks($steps * $interval);
        } else {
            $currentWeek = $anchorWeek->copy();
        }

        while ($currentWeek->lessThanOrEqualTo($windowEnd)) {
            $weeksFromAnchor = $anchorWeek->diffInWeeks($currentWeek, false);

            if ($weeksFromAnchor >= 0 && ($weeksFromAnchor % $interval === 0)) {
                foreach ($weekdays as $isoWeekday) {
                    // isoWeekday: 1=Mon .. 7=Sun
                    $candidate = $currentWeek->copy()->addDays($isoWeekday - 1);

                    if ($candidate->greaterThanOrEqualTo($startDate) &&
                        $candidate->betweenIncluded($windowStart, $windowEnd)) {
                        $dates[] = $candidate;
                    }
                }
            }

            $currentWeek->addWeeks($interval);
        }

        // Sort chronologically
        usort($dates, fn (Carbon $a, Carbon $b) => $a->timestamp <=> $b->timestamp);

        return $dates;
    }

    /**
     * Monthly recurrence by calendar date: e.g. on the 15th every N months.
     *
     * @return list<Carbon>
     */
    protected function evaluateMonthlyDate(RecurringSchedule $schedule, Carbon $startDate, Carbon $windowStart, Carbon $windowEnd): array
    {
        $interval = max(1, (int) ($schedule->interval ?: 1));
        $targetDay = (int) ($schedule->month_day ?: $startDate->day);

        $dates = [];
        $anchorMonth = $startDate->copy()->startOfMonth();
        $startMonth = $windowStart->copy()->startOfMonth();

        $monthsDiff = $anchorMonth->diffInMonths($startMonth, false);
        if ($monthsDiff > 0) {
            $steps = (int) floor($monthsDiff / $interval);
            $currentMonth = $anchorMonth->copy()->addMonths($steps * $interval);
        } else {
            $currentMonth = $anchorMonth->copy();
        }

        while ($currentMonth->lessThanOrEqualTo($windowEnd)) {
            $monthsFromAnchor = $anchorMonth->diffInMonths($currentMonth, false);

            if ($monthsFromAnchor >= 0 && ($monthsFromAnchor % $interval === 0)) {
                // Clamp target day to days in this specific month (e.g., day 31 becomes 28 in Feb)
                $clampedDay = min($targetDay, $currentMonth->daysInMonth);
                $candidate = $currentMonth->copy()->day($clampedDay);

                if ($candidate->greaterThanOrEqualTo($startDate) &&
                    $candidate->betweenIncluded($windowStart, $windowEnd)) {
                    $dates[] = $candidate;
                }
            }

            $currentMonth->addMonths($interval);
        }

        return $dates;
    }

    /**
     * Monthly recurrence by weekday position: e.g. the 2nd Tuesday or last Friday.
     * month_week: 1..4 (1st..4th) or -1 (last)
     * month_weekday: 1 (Mon) .. 7 (Sun)
     *
     * @return list<Carbon>
     */
    protected function evaluateMonthlyDay(RecurringSchedule $schedule, Carbon $startDate, Carbon $windowStart, Carbon $windowEnd): array
    {
        $interval = max(1, (int) ($schedule->interval ?: 1));
        $monthWeek = (int) ($schedule->month_week ?: 1); // 1..4, or -1 for last
        $rawWeekday = (int) ($schedule->month_weekday ?: $startDate->dayOfWeekIso);

        // Normalize weekday for Carbon: CarbonInterface uses 0=Sunday, 1=Monday ... 6=Saturday
        $carbonWeekday = $rawWeekday % 7;

        $dates = [];
        $anchorMonth = $startDate->copy()->startOfMonth();
        $startMonth = $windowStart->copy()->startOfMonth();

        $monthsDiff = $anchorMonth->diffInMonths($startMonth, false);
        if ($monthsDiff > 0) {
            $steps = (int) floor($monthsDiff / $interval);
            $currentMonth = $anchorMonth->copy()->addMonths($steps * $interval);
        } else {
            $currentMonth = $anchorMonth->copy();
        }

        while ($currentMonth->lessThanOrEqualTo($windowEnd)) {
            $monthsFromAnchor = $anchorMonth->diffInMonths($currentMonth, false);

            if ($monthsFromAnchor >= 0 && ($monthsFromAnchor % $interval === 0)) {
                $candidate = null;

                if ($monthWeek === -1) {
                    $candidate = $currentMonth->copy()->lastOfMonth($carbonWeekday);
                } elseif ($monthWeek >= 1 && $monthWeek <= 5) {
                    $c = $currentMonth->copy()->nthOfMonth($monthWeek, $carbonWeekday);
                    if ($c && $c->month === $currentMonth->month) {
                        $candidate = $c;
                    }
                }

                if ($candidate &&
                    $candidate->greaterThanOrEqualTo($startDate) &&
                    $candidate->betweenIncluded($windowStart, $windowEnd)) {
                    $dates[] = $candidate;
                }
            }

            $currentMonth->addMonths($interval);
        }

        return $dates;
    }

    /**
     * Custom list of selected dates.
     *
     * @return list<Carbon>
     */
    protected function evaluateCustomDates(RecurringSchedule $schedule, Carbon $windowStart, Carbon $windowEnd): array
    {
        $selectedDates = $schedule->selected_dates ?? [];
        $tz = $schedule->timezone ?: 'UTC';
        $dates = [];

        foreach ($selectedDates as $dateString) {
            try {
                $candidate = Carbon::parse($dateString, $tz)->startOfDay();
                if ($candidate->betweenIncluded($windowStart, $windowEnd)) {
                    $dates[] = $candidate;
                }
            } catch (\Throwable) {
                // Ignore invalid date format strings
            }
        }

        usort($dates, fn (Carbon $a, Carbon $b) => $a->timestamp <=> $b->timestamp);

        return $dates;
    }
}
