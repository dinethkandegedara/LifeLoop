<?php

namespace App\Http\Controllers;

use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    /**
     * Display the user settings & timezone view.
     */
    public function edit(Request $request, OtpService $otpService): Response
    {
        $user = $request->user();

        $token = $user->getCalendarToken();
        $feedUrl = url("/calendar/feed/{$token}.ics");
        $webcalUrl = preg_replace('/^https?:\/\//', 'webcal://', $feedUrl);

        return Inertia::render('Settings', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'timezone' => $user->timezone ?? 'UTC',
            ],
            'timezones' => timezone_identifiers_list(),
            'emailCooldown' => $otpService->getResendCooldownRemaining($user->email, 'email_change'),
            'passwordCooldown' => $otpService->getResendCooldownRemaining($user->email, 'password_change'),
            'calendarFeed' => [
                'token' => $token,
                'feedUrl' => $feedUrl,
                'webcalUrl' => $webcalUrl,
            ],
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

        return back()->with('success', 'Profile and timezone updated successfully.');
    }

    /**
     * Send OTP to current email to authorize changing account email.
     */
    public function sendEmailOtp(Request $request, OtpService $otpService): RedirectResponse
    {
        $user = $request->user();

        if (!$otpService->canResend($user->email, 'email_change')) {
            $remaining = $otpService->getResendCooldownRemaining($user->email, 'email_change');
            return back()->withErrors([
                'email_otp' => "Please wait {$remaining} seconds before requesting a new verification code.",
            ]);
        }

        $sent = $otpService->generateAndSend($user->email, 'email_change', $user);

        if (!$sent) {
            return back()->withErrors([
                'email_otp' => 'Failed to deliver verification code email. Please try again shortly.',
            ]);
        }

        return back()->with('success', 'Verification code sent to your current email address.');
    }

    /**
     * Update account email after OTP verification.
     */
    public function updateEmail(Request $request, OtpService $otpService): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'new_email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'email_otp' => ['required', 'string', 'size:6'],
        ], [
            'new_email.unique' => 'This email address is already in use by another account.',
            'email_otp.size' => 'The verification code must be 6 digits.',
        ]);

        $result = $otpService->verify($user->email, $validated['email_otp'], 'email_change');

        if (!$result['success']) {
            return back()->withErrors([
                'email_otp' => $result['message'],
            ]);
        }

        $user->update([
            'email' => strtolower(trim($validated['new_email'])),
            'email_verified_at' => now(),
        ]);

        return back()->with('success', 'Your account email address was successfully updated.');
    }

    /**
     * Send OTP to email to authorize password update.
     */
    public function sendPasswordOtp(Request $request, OtpService $otpService): RedirectResponse
    {
        $user = $request->user();

        if (!$otpService->canResend($user->email, 'password_change')) {
            $remaining = $otpService->getResendCooldownRemaining($user->email, 'password_change');
            return back()->withErrors([
                'password_otp' => "Please wait {$remaining} seconds before requesting a new security code.",
            ]);
        }

        $sent = $otpService->generateAndSend($user->email, 'password_change', $user);

        if (!$sent) {
            return back()->withErrors([
                'password_otp' => 'Failed to deliver security code email. Please try again shortly.',
            ]);
        }

        return back()->with('success', 'Security code sent to your email address.');
    }

    /**
     * Update password after email OTP verification.
     */
    public function updatePassword(Request $request, OtpService $otpService): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'password_otp' => ['required', 'string', 'size:6'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ], [
            'password_otp.size' => 'The security code must be 6 digits.',
        ]);

        $result = $otpService->verify($user->email, $validated['password_otp'], 'password_change');

        if (!$result['success']) {
            return back()->withErrors([
                'password_otp' => $result['message'],
            ]);
        }

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('success', 'Password updated successfully.');
    }
}
