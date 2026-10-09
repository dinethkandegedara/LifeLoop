<?php

namespace Tests\Feature\Auth;

use App\Mail\OtpMail;
use App\Models\EmailOtp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register_and_receive_hashed_otp(): void
    {
        Mail::fake();

        $response = $this->post('/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'timezone' => 'America/New_York',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('verification.notice'));

        // Check user record
        $user = User::where('email', 'jane@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('Jane Doe', $user->name);
        $this->assertEquals('America/New_York', $user->timezone);
        $this->assertNull($user->email_verified_at);

        // Check OTP table - verify hash is stored, not plaintext
        $otp = EmailOtp::where('email', 'jane@example.com')->first();
        $this->assertNotNull($otp);
        $this->assertNotEquals('123456', $otp->code_hash);
        $this->assertEquals('verification', $otp->purpose);
        $this->assertEquals(0, $otp->attempts);
        $this->assertNull($otp->consumed_at);

        // Verify Mail was queued/sent
        Mail::assertSent(OtpMail::class, function ($mail) use ($user, $otp) {
            $this->assertTrue(Hash::check($mail->code, $otp->code_hash));
            return $mail->hasTo($user->email) && $mail->purpose === 'verification';
        });
    }

    public function test_registration_validation_fails_on_duplicate_email(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->post('/register', [
            'name' => 'Duplicate User',
            'email' => 'existing@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
