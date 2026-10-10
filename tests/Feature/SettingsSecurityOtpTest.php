<?php

namespace Tests\Feature;

use App\Mail\OtpMail;
use App\Models\EmailOtp;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SettingsSecurityOtpTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_settings_page(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('settings.edit'));
        $response->assertStatus(200);
    }

    public function test_user_can_update_profile_and_timezone(): void
    {
        $user = User::factory()->create([
            'name' => 'Original Name',
            'timezone' => 'UTC',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->put(route('settings.update'), [
            'name' => 'Updated Name',
            'timezone' => 'America/New_York',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertEquals('Updated Name', $user->fresh()->name);
        $this->assertEquals('America/New_York', $user->fresh()->timezone);
    }

    public function test_user_can_send_otp_and_change_email(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'original@example.com',
            'email_verified_at' => now(),
        ]);

        // Request OTP for email change
        $sendResponse = $this->actingAs($user)->post(route('settings.email.send-otp'));
        $sendResponse->assertSessionHas('success');

        Mail::assertSent(OtpMail::class, function ($mail) {
            return $mail->purpose === 'email_change';
        });

        // Fetch generated OTP
        $otpRecord = EmailOtp::where('email', 'original@example.com')
            ->where('purpose', 'email_change')
            ->first();

        $this->assertNotNull($otpRecord);

        // Attempt update with invalid OTP
        $failResponse = $this->actingAs($user)->put(route('settings.email.update'), [
            'new_email' => 'newaddress@example.com',
            'email_otp' => '000000',
        ]);
        $failResponse->assertSessionHasErrors('email_otp');
        $this->assertEquals('original@example.com', $user->fresh()->email);

        // Mock valid OTP code verification
        $validCode = '654321';
        $otpRecord->update([
            'code_hash' => Hash::make($validCode),
            'attempts' => 0,
        ]);

        $successResponse = $this->actingAs($user)->put(route('settings.email.update'), [
            'new_email' => 'newaddress@example.com',
            'email_otp' => $validCode,
        ]);

        $successResponse->assertSessionHas('success');
        $this->assertEquals('newaddress@example.com', $user->fresh()->email);
    }

    public function test_user_can_send_otp_and_change_password(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('old-secure-password'),
            'email_verified_at' => now(),
        ]);

        // Request OTP for password change
        $sendResponse = $this->actingAs($user)->post(route('settings.password.send-otp'));
        $sendResponse->assertSessionHas('success');

        Mail::assertSent(OtpMail::class, function ($mail) {
            return $mail->purpose === 'password_change';
        });

        $otpRecord = EmailOtp::where('email', 'user@example.com')
            ->where('purpose', 'password_change')
            ->first();

        $this->assertNotNull($otpRecord);

        $validCode = '987654';
        $otpRecord->update([
            'code_hash' => Hash::make($validCode),
            'attempts' => 0,
        ]);

        $response = $this->actingAs($user)->put(route('settings.password.update'), [
            'password_otp' => $validCode,
            'password' => 'brand-new-secure-pass-123',
            'password_confirmation' => 'brand-new-secure-pass-123',
        ]);

        $response->assertSessionHas('success');
        $this->assertTrue(Hash::check('brand-new-secure-pass-123', $user->fresh()->password));
    }
}
