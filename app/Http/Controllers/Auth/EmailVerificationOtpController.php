<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmailVerificationOtpController extends Controller
{
    /**
     * Display the email verification OTP screen.
     */
    public function notice(Request $request, OtpService $otpService): Response|RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('today');
        }

        return Inertia::render('Auth/VerifyOtp', [
            'email' => $request->user()->email,
            'cooldownSeconds' => $otpService->getResendCooldownRemaining($request->user()->email, 'verification'),
        ]);
    }

    /**
     * Handle the verification submission.
     */
    public function verify(Request $request, OtpService $otpService): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('today');
        }

        $result = $otpService->verify($user->email, $request->code, 'verification');

        if (!$result['success']) {
            return back()->withErrors([
                'code' => $result['message'],
            ]);
        }

        $user->markEmailAsVerified();

        return redirect()->route('today')->with('success', 'Email verified successfully! Welcome to LifeLoop.');
    }

    /**
     * Resend the verification OTP.
     */
    public function resend(Request $request, OtpService $otpService): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('today');
        }

        if (!$otpService->canResend($user->email, 'verification')) {
            $remaining = $otpService->getResendCooldownRemaining($user->email, 'verification');
            return back()->withErrors([
                'code' => "Please wait {$remaining} seconds before requesting a new code.",
            ]);
        }

        $otpService->generateAndSend($user->email, 'verification', $user);

        return back()->with('success', 'A new verification code has been sent to your email.');
    }
}
