<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_session_lifetime_is_configured_for_24_hours_of_inactivity(): void
    {
        // 24 hours * 60 minutes = 1440 minutes
        $this->assertEquals(1440, config('session.lifetime'));
    }

    public function test_persistent_cookie_does_not_expire_on_browser_close(): void
    {
        $this->assertFalse(config('session.expire_on_close'));
    }

    public function test_session_cookie_security_attributes_are_strictly_configured(): void
    {
        $this->assertTrue(config('session.http_only'));
        $this->assertEquals('lax', config('session.same_site'));
    }

    public function test_login_issues_24_hour_persistent_remember_cookie(): void
    {
        $user = \App\Models\User::factory()->create([
            'email_verified_at' => now(),
            'password' => \Illuminate\Support\Facades\Hash::make('password123'),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
            'remember' => true,
        ]);

        $response->assertRedirect(route('today'));
        $this->assertAuthenticatedAs($user);

        // Verify recaller cookie exists and has max-age of 86400 seconds (24 hours)
        $cookies = $response->headers->getCookies();
        $rememberCookie = collect($cookies)->first(fn ($c) => str_starts_with($c->getName(), 'remember_web_'));

        $this->assertNotNull($rememberCookie, 'Remember cookie should be set');
        $this->assertEquals(86400, $rememberCookie->getMaxAge(), 'Remember cookie should have 24-hour Max-Age (86400s)');
    }

    public function test_registration_issues_24_hour_persistent_remember_cookie(): void
    {
        $response = $this->post('/register', [
            'name' => 'Persist User',
            'email' => 'persist@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'timezone' => 'UTC',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $this->assertAuthenticated();

        $cookies = $response->headers->getCookies();
        $rememberCookie = collect($cookies)->first(fn ($c) => str_starts_with($c->getName(), 'remember_web_'));

        $this->assertNotNull($rememberCookie, 'Remember cookie should be set upon registration');
        $this->assertEquals(86400, $rememberCookie->getMaxAge(), 'Remember cookie should have 24-hour Max-Age (86400s)');
    }

    public function test_user_is_reauthenticated_via_remember_cookie_when_session_is_missing(): void
    {
        $user = \App\Models\User::factory()->create([
            'email_verified_at' => now(),
            'password' => \Illuminate\Support\Facades\Hash::make('password123'),
        ]);

        $loginResponse = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
            'remember' => true,
        ]);

        $cookies = $loginResponse->headers->getCookies();
        $rememberCookie = collect($cookies)->first(fn ($c) => str_starts_with($c->getName(), 'remember_web_'));

        $this->flushSession();
        \Illuminate\Support\Facades\Auth::forgetGuards();

        $freshResponse = $this->withUnencryptedCookie($rememberCookie->getName(), $rememberCookie->getValue())
            ->get('/');

        $freshResponse->assertOk();
        $this->assertAuthenticatedAs($user);
    }
}
