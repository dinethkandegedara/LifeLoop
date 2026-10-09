<?php

namespace Tests\Feature\Auth;

use App\Mail\OtpMail;
use App\Models\EmailOtp;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailVerificationOtpTest extends TestCase
{
    use RefreshDatabase;

    public function test_verification_screen_can_be_rendered_for_unverified_user(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get(route('verification.notice'));

        $response->assertStatus(200);
    }

    public function test_already_verified_user_is_redirected_away_from_verification_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('verification.notice'));

        $response->assertRedirect(route('today'));
    }

    public function test_email_can_be_verified_with_valid_otp(): void
    {
        $user = User::factory()->unverified()->create();
        $code = '654321';

        EmailOtp::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'code_hash' => Hash::make($code),
            'purpose' => 'verification',
            'expires_at' => now()->addMinutes(5),
            'attempts' => 0,
        ]);

        $response = $this->actingAs($user)->post(route('verification.verify'), [
            'code' => $code,
        ]);

        $response->assertRedirect(route('today'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        // Verify OTP is marked consumed
        $otp = EmailOtp::where('email', $user->email)->first();
        $this->assertNotNull($otp->consumed_at);
    }

    public function test_consumed_otp_cannot_be_reused(): void
    {
        $user = User::factory()->unverified()->create();
        $code = '112233';

        $otp = EmailOtp::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'code_hash' => Hash::make($code),
            'purpose' => 'verification',
            'expires_at' => now()->addMinutes(5),
            'attempts' => 0,
            'consumed_at' => now(), // Already consumed
        ]);

        $response = $this->actingAs($user)->post(route('verification.verify'), [
            'code' => $code,
        ]);

        $response->assertSessionHasErrors('code');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_email_cannot_be_verified_with_invalid_otp(): void
    {
        $user = User::factory()->unverified()->create();
        $code = '123456';

        EmailOtp::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'code_hash' => Hash::make($code),
            'purpose' => 'verification',
            'expires_at' => now()->addMinutes(5),
            'attempts' => 0,
        ]);

        $response = $this->actingAs($user)->post(route('verification.verify'), [
            'code' => '999999',
        ]);

        $response->assertSessionHasErrors('code');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());

        $otp = EmailOtp::where('email', $user->email)->first();
        $this->assertEquals(1, $otp->attempts);
    }

    public function test_otp_is_invalidated_after_max_attempts(): void
    {
        $user = User::factory()->unverified()->create();
        $code = '123456';

        EmailOtp::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'code_hash' => Hash::make($code),
            'purpose' => 'verification',
            'expires_at' => now()->addMinutes(5),
            'attempts' => 5, // Max attempts reached
        ]);

        $response = $this->actingAs($user)->post(route('verification.verify'), [
            'code' => $code, // Even with correct code, must fail because attempts exceeded
        ]);

        $response->assertSessionHasErrors('code');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_expired_otp_cannot_be_used(): void
    {
        $user = User::factory()->unverified()->create();
        $code = '123456';

        EmailOtp::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'code_hash' => Hash::make($code),
            'purpose' => 'verification',
            'expires_at' => now()->subMinute(), // Expired
            'attempts' => 0,
        ]);

        $response = $this->actingAs($user)->post(route('verification.verify'), [
            'code' => $code,
        ]);

        $response->assertSessionHasErrors('code');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_resend_otp_enforces_rate_limit(): void
    {
        Mail::fake();
        $user = User::factory()->unverified()->create();

        // Create recent OTP 10 seconds ago
        EmailOtp::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'code_hash' => Hash::make('111111'),
            'purpose' => 'verification',
            'expires_at' => now()->addMinutes(5),
            'attempts' => 0,
            'created_at' => now()->subSeconds(10),
        ]);

        $response = $this->actingAs($user)->post(route('verification.resend'));

        $response->assertSessionHasErrors('code');
        Mail::assertNothingSent();
    }

    public function test_resend_otp_invalidates_previous_active_otps(): void
    {
        Mail::fake();
        $user = User::factory()->unverified()->create();

        $oldOtp = EmailOtp::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'code_hash' => Hash::make('111111'),
            'purpose' => 'verification',
            'expires_at' => now()->addMinutes(5),
            'attempts' => 0,
        ]);

        // Advance time past cooldown
        $this->travel(70)->seconds();

        $response = $this->actingAs($user)->post(route('verification.resend'));

        $response->assertSessionHasNoErrors();
        Mail::assertSent(OtpMail::class);

        // Previous OTP must be consumed/invalidated
        $this->assertNotNull($oldOtp->fresh()->consumed_at);

        // New OTP exists
        $newOtp = EmailOtp::where('email', $user->email)->whereNull('consumed_at')->first();
        $this->assertNotNull($newOtp);
        $this->assertNotEquals($oldOtp->id, $newOtp->id);
    }
}
