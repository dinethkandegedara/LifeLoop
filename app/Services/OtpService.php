<?php

namespace App\Services;

use App\Mail\OtpMail;
use App\Models\EmailOtp;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class OtpService
{
    /**
     * Expiration time in minutes.
     */
    public const EXPIRATION_MINUTES = 5;

    /**
     * Minimum interval between resends in seconds.
     */
    public const RESEND_COOLDOWN_SECONDS = 60;

    /**
     * Maximum allowed verification attempts per OTP.
     */
    public const MAX_ATTEMPTS = 5;

    /**
     * Check if a new OTP can be sent to this email for the given purpose.
     */
    public function canResend(string $email, string $purpose = 'verification'): bool
    {
        return !EmailOtp::where('email', strtolower(trim($email)))
            ->where('purpose', $purpose)
            ->where('created_at', '>=', now()->subSeconds(self::RESEND_COOLDOWN_SECONDS))
            ->exists();
    }

    /**
     * Get remaining cooldown seconds before a resend is allowed.
     */
    public function getResendCooldownRemaining(string $email, string $purpose = 'verification'): int
    {
        $recent = EmailOtp::where('email', strtolower(trim($email)))
            ->where('purpose', $purpose)
            ->latest()
            ->first();

        if (!$recent) {
            return 0;
        }

        $elapsed = now()->diffInSeconds($recent->created_at);
        return max(0, self::RESEND_COOLDOWN_SECONDS - $elapsed);
    }

    /**
     * Generate a new 6-digit OTP, store its hash, invalidate older OTPs, and return the plaintext code.
     */
    public function generateAndSend(string $email, string $purpose = 'verification', ?User $user = null): bool
    {
        $normalizedEmail = strtolower(trim($email));

        // Invalidate or supersede older active OTPs for this email and purpose
        EmailOtp::where('email', $normalizedEmail)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        // Generate cryptographically secure 6-digit numeric OTP
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store hash of the OTP, never plaintext
        EmailOtp::create([
            'user_id' => $user?->id,
            'email' => $normalizedEmail,
            'code_hash' => Hash::make($code),
            'purpose' => $purpose,
            'expires_at' => now()->addMinutes(self::EXPIRATION_MINUTES),
            'attempts' => 0,
            'consumed_at' => null,
        ]);

        // Send email (never log code)
        try {
            Mail::to($normalizedEmail)->send(new OtpMail($code, $purpose));
            return true;
        } catch (\Throwable $e) {
            report($e);
            return false;
        }
    }

    /**
     * Verify an OTP code.
     *
     * @return array{success: bool, message?: string, otp?: EmailOtp}
     */
    public function verify(string $email, string $code, string $purpose = 'verification'): array
    {
        $normalizedEmail = strtolower(trim($email));

        // Find the latest active unconsumed OTP
        $otp = EmailOtp::where('email', $normalizedEmail)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->latest()
            ->first();

        if (!$otp) {
            return [
                'success' => false,
                'message' => 'No active verification code was found. Please request a new code.',
            ];
        }

        // Check if expired
        if ($otp->isExpired()) {
            $otp->update(['consumed_at' => now()]);
            return [
                'success' => false,
                'message' => 'This verification code has expired. Please request a new code.',
            ];
        }

        // Check attempt count
        if ($otp->isMaxAttemptsExceeded()) {
            $otp->update(['consumed_at' => now()]);
            return [
                'success' => false,
                'message' => 'Too many incorrect attempts. This code has been invalidated. Please request a new code.',
            ];
        }

        // Increment attempts count
        $otp->increment('attempts');

        // Check hash
        if (!Hash::check($code, $otp->code_hash)) {
            $remaining = max(0, self::MAX_ATTEMPTS - $otp->attempts);
            return [
                'success' => false,
                'message' => "Invalid code. {$remaining} attempts remaining.",
            ];
        }

        // Mark consumed immediately
        $otp->update(['consumed_at' => now()]);

        return [
            'success' => true,
            'otp' => $otp,
        ];
    }
}
