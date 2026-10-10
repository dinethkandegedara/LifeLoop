<?php

namespace App\Services\Habits;

use App\Models\ScheduleOccurrence;
use App\Models\User;
use App\Models\WorkSession;
use App\Services\Reporting\ReportCacheService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class StreakService
{
    /**
     * Threshold for a day to qualify towards a streak (80% occurrence completion).
     */
    public const COMPLETION_THRESHOLD = 0.80;

    /**
     * Calculate streak and consistency data for a user.
     *
     * @return array{
     *     current_streak: int,
     *     best_streak: int,
     *     today_completion_rate: float,
     *     today_qualified: bool,
     *     today_scheduled_count: int,
     *     today_completed_count: int,
     *     label: string,
     *     subtext: string,
     *     badge_name: string
     * }
     */
    public function getStreakData(User $user): array
    {
        $cacheKey = ReportCacheService::streakCacheKey($user);

        return Cache::remember($cacheKey, ReportCacheService::DEFAULT_TTL_SECONDS, function () use ($user) {
            $tz = $user->timezone ?: 'UTC';
            $today = now($tz)->startOfDay();
            $todayDateStr = $today->toDateString();

        // Examine past 90 days of history
        $startDate = $today->copy()->subDays(90);
        $startDateStr = $startDate->toDateString();

        // 1. Group occurrences by date: total eligible and completed count
        $occurrencesByDate = ScheduleOccurrence::where('user_id', $user->id)
            ->where('status', '!=', 'cancelled')
            ->whereBetween('scheduled_date', [$startDateStr, $todayDateStr])
            ->selectRaw("
                scheduled_date,
                COUNT(*) as total_count,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_count
            ")
            ->groupBy('scheduled_date')
            ->get()
            ->keyBy(fn ($item) => Carbon::parse($item->scheduled_date)->toDateString());

        // 2. Group work sessions by date (user timezone)
        $rangeStartUtc = $startDate->copy()->startOfDay()->toDateTimeString();
        $rangeEndUtc = $today->copy()->endOfDay()->toDateTimeString();

        $workByDate = WorkSession::where('user_id', $user->id)
            ->whereBetween('started_at', [$rangeStartUtc, $rangeEndUtc])
            ->selectRaw("
                DATE(started_at) as work_date,
                COALESCE(SUM(duration_minutes), 0) as total_minutes
            ")
            ->groupBy('work_date')
            ->get()
            ->keyBy('work_date');

        // Helper to check if a specific date qualifies
        $checkDateQualifies = function (string $dateStr) use ($occurrencesByDate, $workByDate): array {
            $occStats = $occurrencesByDate->get($dateStr);
            $workStats = $workByDate->get($dateStr);

            $totalOcc = (int) ($occStats->total_count ?? 0);
            $completedOcc = (int) ($occStats->completed_count ?? 0);
            $workMinutes = (int) ($workStats->total_minutes ?? 0);

            if ($totalOcc > 0) {
                $rate = $completedOcc / $totalOcc;
                $qualifies = $rate >= self::COMPLETION_THRESHOLD;
                return [
                    'qualifies' => $qualifies,
                    'total_count' => $totalOcc,
                    'completed_count' => $completedOcc,
                    'rate' => round($rate * 100, 1),
                ];
            }

            // If no occurrences scheduled, 30+ minutes of recorded focus work qualifies
            if ($workMinutes >= 30) {
                return [
                    'qualifies' => true,
                    'total_count' => 0,
                    'completed_count' => 0,
                    'rate' => 100.0,
                ];
            }

            return [
                'qualifies' => false,
                'total_count' => 0,
                'completed_count' => 0,
                'rate' => 0.0,
            ];
        };

        // Today's metrics
        $todayInfo = $checkDateQualifies($todayDateStr);
        $todayQualified = $todayInfo['qualifies'];

        // Calculate current consecutive streak:
        // Start from today if today qualifies; otherwise start from yesterday so today in-progress doesn't break streak
        $currentStreak = 0;
        $evalDate = $todayQualified ? $today->copy() : $today->copy()->subDay();

        while ($evalDate->gte($startDate)) {
            $info = $checkDateQualifies($evalDate->toDateString());
            if ($info['qualifies']) {
                $currentStreak++;
                $evalDate->subDay();
            } else {
                break;
            }
        }

        // Calculate best streak in the 90-day window
        $bestStreak = 0;
        $runningStreak = 0;
        $curDate = $startDate->copy();

        while ($curDate->lte($today)) {
            $info = $checkDateQualifies($curDate->toDateString());
            if ($info['qualifies']) {
                $runningStreak++;
                if ($runningStreak > $bestStreak) {
                    $bestStreak = $runningStreak;
                }
            } else {
                $runningStreak = 0;
            }
            $curDate->addDay();
        }

        if ($currentStreak > $bestStreak) {
            $bestStreak = $currentStreak;
        }

        // Determine badge name based on streak length
        $badgeName = match (true) {
            $currentStreak >= 30 => 'Diamond Consistency',
            $currentStreak >= 14 => 'Gold Master',
            $currentStreak >= 7 => 'Silver Achiever',
            $currentStreak >= 3 => 'Bronze Momentum',
            $currentStreak >= 1 => 'Active Streak',
            default => 'Ready to Start',
        };

        // User-facing label and subtext
        if ($currentStreak > 0) {
            $label = "{$currentStreak} days in a row with ≥80% completion";
            $subtext = $todayQualified
                ? 'Streak secured for today! 🔥'
                : 'Complete ≥80% of today\'s scheduled tasks to keep the streak alive!';
        } else {
            $label = 'Daily Consistency Goal';
            $subtext = 'Complete at least 80% of scheduled tasks today to start a streak!';
        }

            return [
                'current_streak' => $currentStreak,
                'best_streak' => $bestStreak,
                'today_completion_rate' => $todayInfo['rate'],
                'today_qualified' => $todayQualified,
                'today_scheduled_count' => $todayInfo['total_count'],
                'today_completed_count' => $todayInfo['completed_count'],
                'label' => $label,
                'subtext' => $subtext,
                'badge_name' => $badgeName,
            ];
        });
    }
}
