<?php

namespace App\Services\Reporting;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class ReportCacheService
{
    /**
     * Fallback TTL for cached reports (30 minutes).
     * Correctness is guaranteed through event-driven version invalidation.
     */
    public const DEFAULT_TTL_SECONDS = 1800;

    /**
     * Get the current report cache version for a specific user.
     */
    public static function getUserVersion(int $userId): int
    {
        return (int) Cache::get("user:{$userId}:report_cache_version", 1);
    }

    /**
     * Invalidate all cached reports and streak metrics for a specific user in O(1) time.
     * Compatible with file, database, redis, and array cache stores.
     */
    public static function invalidateUser(int $userId): void
    {
        $current = static::getUserVersion($userId);
        Cache::put("user:{$userId}:report_cache_version", $current + 1, now()->addDays(30));
    }

    /**
     * Generate a deterministic, user-isolated cache key for overview reports.
     */
    public static function overviewCacheKey(
        User $user,
        string $period,
        ?Carbon $customStart = null,
        ?Carbon $customEnd = null,
        ?int $taskId = null,
        ?Carbon $anchorDate = null
    ): string {
        $version = static::getUserVersion($user->id);
        $tz = $user->timezone ?: 'UTC';
        $anchor = $anchorDate ? $anchorDate->toDateString() : 'default';
        $start = $customStart ? $customStart->toDateString() : 'none';
        $end = $customEnd ? $customEnd->toDateString() : 'none';
        $task = $taskId ?? 'all';
        $tzHash = substr(md5($tz), 0, 8);

        return "report:u{$user->id}:v{$version}:overview:{$period}:a{$anchor}:s{$start}:e{$end}:t{$task}:{$tzHash}";
    }

    /**
     * Generate a deterministic, user-isolated cache key for task reports.
     */
    public static function taskReportCacheKey(
        User $user,
        int $taskId,
        string $period,
        ?Carbon $customStart = null,
        ?Carbon $customEnd = null,
        ?string $statusFilter = null,
        ?Carbon $anchorDate = null
    ): string {
        $version = static::getUserVersion($user->id);
        $tz = $user->timezone ?: 'UTC';
        $anchor = $anchorDate ? $anchorDate->toDateString() : 'default';
        $start = $customStart ? $customStart->toDateString() : 'none';
        $end = $customEnd ? $customEnd->toDateString() : 'none';
        $status = $statusFilter ?? 'all';
        $tzHash = substr(md5($tz), 0, 8);

        return "report:u{$user->id}:v{$version}:task:{$taskId}:{$period}:a{$anchor}:s{$start}:e{$end}:st{$status}:{$tzHash}";
    }

    /**
     * Generate a deterministic, user-isolated cache key for streak metrics.
     */
    public static function streakCacheKey(User $user): string
    {
        $version = static::getUserVersion($user->id);
        $tz = $user->timezone ?: 'UTC';
        $today = now($tz)->toDateString();
        $tzHash = substr(md5($tz), 0, 8);

        return "streak:u{$user->id}:v{$version}:{$today}:{$tzHash}";
    }
}
