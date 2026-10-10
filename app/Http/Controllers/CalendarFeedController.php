<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Calendar\CalendarSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CalendarFeedController extends Controller
{
    /**
     * Public read-only tokenized iCalendar (.ics / webcal) feed.
     */
    public function feed(string $token, CalendarSyncService $syncService): Response
    {
        // Token must be valid 64-character hex string
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            abort(404, 'Calendar feed not found or invalid link.');
        }

        $user = User::where('calendar_token', $token)->first();

        if (!$user) {
            abort(404, 'Calendar feed not found or invalid link.');
        }

        $ics = $syncService->generateIcs($user);

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="lifeloop-schedule.ics"',
            'Cache-Control' => 'no-cache, no-store, max-age=0, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * Regenerate the authenticated user's calendar feed token.
     */
    public function regenerate(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->regenerateCalendarToken();

        return back()->with('success', 'Your personal calendar link has been regenerated. Previous subscription links have been invalidated.');
    }
}
