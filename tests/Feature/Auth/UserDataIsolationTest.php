<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserDataIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_users_are_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/settings')->assertRedirect('/login');
        $this->get('/foundation')->assertRedirect('/login');
    }

    public function test_authenticated_user_can_only_update_their_own_profile_and_timezone(): void
    {
        $userA = User::factory()->create([
            'name' => 'User A',
            'timezone' => 'UTC',
        ]);

        $userB = User::factory()->create([
            'name' => 'User B',
            'timezone' => 'America/Chicago',
        ]);

        // User A performs update
        $response = $this->actingAs($userA)->put('/settings', [
            'name' => 'User A Updated',
            'timezone' => 'Asia/Tokyo',
        ]);

        $response->assertSessionHasNoErrors();

        // Verify User A was updated
        $this->assertEquals('User A Updated', $userA->fresh()->name);
        $this->assertEquals('Asia/Tokyo', $userA->fresh()->timezone);

        // Verify User B was NOT modified
        $this->assertEquals('User B', $userB->fresh()->name);
        $this->assertEquals('America/Chicago', $userB->fresh()->timezone);
    }
}
