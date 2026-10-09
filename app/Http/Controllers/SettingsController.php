<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    /**
     * Display the user settings & timezone view.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Settings', [
            'user' => [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'email' => $request->user()->email,
                'timezone' => $request->user()->timezone ?? 'UTC',
            ],
            'timezones' => timezone_identifiers_list(),
        ]);
    }

    /**
     * Update the authenticated user's profile and timezone.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'timezone' => ['required', 'string', 'max:64', 'timezone'],
        ]);

        // Explicit user data isolation: update only the authenticated user
        $request->user()->update([
            'name' => $validated['name'],
            'timezone' => $validated['timezone'],
        ]);

        return back()->with('success', 'Settings and timezone updated successfully.');
    }
}
