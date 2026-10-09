<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetOtpController extends Controller
{
    /**
     * Display the forgot password view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    /**
     * Handle the request to send a password reset OTP.
     */
    public function sendOtp(Request $request, OtpService $otpService): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $throttleKey = 'password-reset|'.Str::lower($request->email).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 4)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'email' => "Too many attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        RateLimiter::hit($throttleKey, 60);

        $normalizedEmail = strtolower(trim($request->email));
        $user = User::where('email', $normalizedEmail)->first();

        // Do not reveal whether email exists; send OTP only if user exists and cooldown has passed
        if ($user && $otpService->canResend($user->email, 'password_reset')) {
            $otpService->generateAndSend($user->email, 'password_reset', $user);
        }

        return redirect()->route('password.reset', ['email' => $normalizedEmail])
            ->with('success', 'If an account exists with that email, a 6-digit reset code has been sent.');
    }

    /**
     * Display the reset password view with code input.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'email' => $request->query('email', ''),
        ]);
    }

    /**
     * Handle the password reset submission.
     */
    public function reset(Request $request, OtpService $otpService): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'code' => ['required', 'string', 'size:6'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $normalizedEmail = strtolower(trim($request->email));

        $result = $otpService->verify($normalizedEmail, $request->code, 'password_reset');

        if (!$result['success']) {
            return back()->withErrors([
                'code' => $result['message'],
            ]);
        }

        $user = User::where('email', $normalizedEmail)->first();

        if (!$user) {
            return back()->withErrors([
                'email' => 'Unable to reset password for this email address.',
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($request->password),
            'remember_token' => Str::random(60),
        ])->save();

        return redirect()->route('login')->with('success', 'Password reset successfully! Please log in with your new password.');
    }
}
