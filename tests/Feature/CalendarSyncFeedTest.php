<?php

namespace Tests\Feature;

use App\Models\ScheduleOccurrence;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarSyncFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_calendar_feed_returns_valid_ical_data(): void
    {
        $user = User::factory()->create([
            'name' => 'Alex Morgan',
            'timezone' => 'America/New_York',
        ]);

        $task = Task::factory()->create([
            'user_id' => $user->id,
            'title' => 'Deep Work Session',
            'description' => 'Focus time on core algorithms',
        ]);

        $occurrenceDate = now('America/New_York')->addDay()->toDateString();

        $occ = ScheduleOccurrence::factory()->create([
            'user_id' => $user->id,
            'task_id' => $task->id,
            'scheduled_date' => $occurrenceDate,
            'start_time' => '10:00:00',
            'duration_minutes' => 90,
            'status' => 'pending',
        ]);

        $token = $user->getCalendarToken();
        $this->assertNotEmpty($token);

        // Fetch public iCal feed without authentication headers
        $response = $this->get("/calendar/feed/{$token}.ics");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/calendar; charset=utf-8');

        $content = $response->getContent();

        $this->assertStringContainsString('BEGIN:VCALENDAR', $content);
        $this->assertStringContainsString('VERSION:2.0', $content);
        $this->assertStringContainsString('PRODID:-//LifeLoop//Personal Schedule Tracker//EN', $content);
        $this->assertStringContainsString('X-WR-CALNAME:LifeLoop - Alex Morgan', $content);
        $this->assertStringContainsString('X-WR-TIMEZONE:America/New_York', $content);
        $this->assertStringContainsString('BEGIN:VEVENT', $content);
        $this->assertStringContainsString('SUMMARY:Deep Work Session', $content);
        $this->assertStringContainsString('DESCRIPTION:Focus time on core algorithms', $content);
        $this->assertStringContainsString('STATUS:CONFIRMED', $content);
        $this->assertStringContainsString('END:VEVENT', $content);
        $this->assertStringContainsString('END:VCALENDAR', $content);
    }

    public function test_user_data_isolation_in_calendar_feed(): void
    {
        $userA = User::factory()->create(['name' => 'User A']);
        $userB = User::factory()->create(['name' => 'User B']);

        $taskA = Task::factory()->create(['user_id' => $userA->id, 'title' => 'Private Secret Task A']);
        $taskB = Task::factory()->create(['user_id' => $userB->id, 'title' => 'Private Secret Task B']);

        $date = now()->addDays(2)->toDateString();

        ScheduleOccurrence::factory()->create([
            'user_id' => $userA->id,
            'task_id' => $taskA->id,
            'scheduled_date' => $date,
            'start_time' => '09:00:00',
            'duration_minutes' => 60,
        ]);

        ScheduleOccurrence::factory()->create([
            'user_id' => $userB->id,
            'task_id' => $taskB->id,
            'scheduled_date' => $date,
            'start_time' => '11:00:00',
            'duration_minutes' => 60,
        ]);

        $tokenA = $userA->getCalendarToken();

        $responseA = $this->get("/calendar/feed/{$tokenA}.ics");
        $responseA->assertStatus(200);
        $contentA = $responseA->getContent();

        $this->assertStringContainsString('Private Secret Task A', $contentA);
        $this->assertStringNotContainsString('Private Secret Task B', $contentA);
    }

    public function test_invalid_or_nonexistent_token_returns_404(): void
    {
        // Malformed token
        $badToken = 'not-a-valid-token';
        $response = $this->get("/calendar/feed/{$badToken}.ics");
        $response->assertStatus(404);

        // Valid length but non-existent hex token
        $fakeToken = str_repeat('a', 64);
        $response2 = $this->get("/calendar/feed/{$fakeToken}.ics");
        $response2->assertStatus(404);
    }

    public function test_user_can_regenerate_calendar_token(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $oldToken = $user->getCalendarToken();

        // Old token works
        $responseOld = $this->get("/calendar/feed/{$oldToken}.ics");
        $responseOld->assertStatus(200);

        // Regenerate token via authenticated endpoint
        $regenerateResponse = $this->actingAs($user)->post('/settings/calendar-feed/regenerate');
        $regenerateResponse->assertSessionHas('success');

        $newToken = $user->fresh()->calendar_token;
        $this->assertNotEquals($oldToken, $newToken);

        // Old token is now invalid (404)
        $responseOldAfter = $this->get("/calendar/feed/{$oldToken}.ics");
        $responseOldAfter->assertStatus(404);

        // New token works
        $responseNew = $this->get("/calendar/feed/{$newToken}.ics");
        $responseNew->assertStatus(200);
    }
}
