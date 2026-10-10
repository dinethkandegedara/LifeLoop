<?php

namespace App\Services\Calendar;

use App\Models\ScheduleOccurrence;
use App\Models\User;
use App\Services\Scheduling\ScheduleManager;
use Carbon\Carbon;

class CalendarSyncService
{
    public function __construct(
        protected ScheduleManager $scheduleManager
    ) {}

    /**
     * Generate RFC 5545 compliant iCalendar string for a user's occurrences.
     */
    public function generateIcs(User $user): string
    {
        $tz = $user->timezone ?: 'UTC';
        $now = now($tz);
        $dtStamp = now('UTC')->format('Ymd\THis\Z');
        $domain = parse_url(config('app.url'), PHP_URL_HOST) ?: 'lifeloop.app';

        // Horizon: 30 days past, 60 days future
        $rangeStart = $now->copy()->subDays(30)->startOfDay();
        $rangeEnd = $now->copy()->addDays(60)->endOfDay();

        // Ensure future recurring occurrences are materialized
        $this->scheduleManager->getOccurrencesForRange($user, $rangeStart, $rangeEnd);

        // Fetch occurrences
        $occurrences = ScheduleOccurrence::where('user_id', $user->id)
            ->where('status', '!=', 'cancelled')
            ->whereBetween('scheduled_date', [$rangeStart->toDateString(), $rangeEnd->toDateString()])
            ->with(['task', 'recurringSchedule'])
            ->orderBy('scheduled_date')
            ->orderBy('start_time')
            ->get();

        $userName = $this->escapeIcsString($user->name);
        $calName = "LifeLoop - {$userName}";

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//LifeLoop//Personal Schedule Tracker//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            "X-WR-CALNAME:{$calName}",
            "X-WR-TIMEZONE:{$tz}",
            'X-WR-CALDESC:Scheduled tasks and work sessions synchronized from LifeLoop',
            'REFRESH-INTERVAL;VALUE=DURATION:PT1H',
            'X-PUBLISHED-TTL:PT1H',
        ];

        foreach ($occurrences as $occ) {
            $taskTitle = $occ->task ? $occ->task->title : 'Scheduled Task';
            $summary = $this->escapeIcsString($taskTitle);

            // Compute UTC start and end timestamps
            $dateStr = $occ->scheduled_date instanceof \DateTimeInterface
                ? $occ->scheduled_date->format('Y-m-d')
                : substr((string) $occ->scheduled_date, 0, 10);
            $timeStr = substr((string) $occ->start_time, 0, 8);

            $startDateTime = Carbon::parse("{$dateStr} {$timeStr}", $tz);
            $endDateTime = $startDateTime->copy()->addMinutes((int) $occ->duration_minutes);

            $dtStartUtc = $startDateTime->copy()->utc()->format('Ymd\THis\Z');
            $dtEndUtc = $endDateTime->copy()->utc()->format('Ymd\THis\Z');

            $statusStr = match ($occ->status) {
                'completed' => 'COMPLETED',
                default => 'CONFIRMED',
            };

            $descLines = [];
            if ($occ->task && $occ->task->description) {
                $descLines[] = $occ->task->description;
            }
            $descLines[] = "Duration: {$occ->duration_minutes} minutes";
            $descLines[] = "Status: " . ucfirst($occ->status);
            if ($occ->status === 'completed' && $occ->completed_at) {
                $descLines[] = "Completed at: " . Carbon::parse($occ->completed_at, $tz)->format('M d, Y H:i');
            }
            if ($occ->notes) {
                $descLines[] = "Notes: {$occ->notes}";
            }
            $description = $this->escapeIcsString(implode("\n", $descLines));

            $uid = "lifeloop-occ-{$occ->id}-{$dateStr}@{$domain}";

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = "UID:{$uid}";
            $lines[] = "DTSTAMP:{$dtStamp}";
            $lines[] = "DTSTART:{$dtStartUtc}";
            $lines[] = "DTEND:{$dtEndUtc}";
            $lines[] = "SUMMARY:{$summary}";
            if (!empty($description)) {
                $lines[] = "DESCRIPTION:{$description}";
            }
            $lines[] = "STATUS:{$statusStr}";
            $lines[] = 'CLASS:PUBLIC';
            $lines[] = 'TRANSP:OPAQUE';
            $lines[] = 'SEQUENCE:0';
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        // RFC 5545 requires CRLF line endings
        return implode("\r\n", $lines) . "\r\n";
    }

    /**
     * Escape text values for iCalendar format.
     */
    protected function escapeIcsString(string $text): string
    {
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace(';', '\;', $text);
        $text = str_replace(',', '\,', $text);
        $text = str_replace("\r\n", '\n', $text);
        $text = str_replace("\n", '\n', $text);
        $text = str_replace("\r", '\n', $text);

        return $text;
    }
}
