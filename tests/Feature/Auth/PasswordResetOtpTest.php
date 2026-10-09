<?php

namespace Tests\Feature\Auth;

use App\Mail\OtpMail;
use App\Models\EmailOtp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordResetOtpTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_password_reset_otp_is_sent_for_existing_user(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $response = $this->post('/forgot-password', [
            'email' => $user->email,
        ]);

        $response->assertRedirect(route('password.reset', ['email' => $user->email]));
        $response->assertSessionHas('success');

        Mail::assertSent(OtpMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email) && $mail->purpose === 'password_reset';
        });

        $this->assertDatabaseHas('email_otps', [
            'email' => $user->email,
            'purpose' => 'password_reset',
        ]);
    }

    public function test_password_reset_request_for_nonexistent_email_returns_identical_opaque_response(): void
    {
        Mail::fake();

        $response = $this->post('/forgot-password', [
            'email' => 'unknown@example.com',
        ]);

        // Must redirect with exact same message, no user enumeration
        $response->assertRedirect(route('password.reset', ['email' => 'unknown@example.com']));
        $response->assertSessionHas('success');

        Mail::assertNothingSent();
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        $response = $this->get('/reset-password?email=test@example.com');

        $response->assertStatus(200);
    }

    public function test_password_can_be_reset_with_valid_otp(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('old-password'),
        ]);

        $code = '789123';
        EmailOtp::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'code_hash' => Hash::make($code),
            'purpose' => 'password_reset',
            'expires_at' => now()->addMinutes(5),
            'attempts' => 0,
        ]);

        $response = $this->post('/reset-password', [
            'email' => $user->email,
            'code' => $code,
            'password' => 'NewSecretPassword123!',
            'password_confirmation' => 'NewSecretPassword123!',
        ]);

        $response->assertRedirect('/login');
        $this->assertTrue(Hash::check('NewSecretPassword123!', $user->fresh()->password));

        // OTP must be marked consumed
        $this->assertNotNull(EmailOtp::where('email', $user->email)->where('purpose', 'password_reset')->first()->consumed_at);
    }

    public function test_password_cannot_be_reset_with_invalid_otp(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('old-password'),
        ]);

        EmailOtp::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'code_hash' => Hash::make('123456'),
            'purpose' => 'password_reset',
            'expires_at' => now()->addMinutes(5),
            'attempts' => 0,
        ]);

        $response = $this->post('/reset-password', [
            'email' => $user->email,
            'code' => '999999',
            'password' => 'NewSecretPassword123!',
            'password_confirmation' => 'NewSecretPassword123!',
        ]);

        $response->assertSessionHasErrors('code');
        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }
}
