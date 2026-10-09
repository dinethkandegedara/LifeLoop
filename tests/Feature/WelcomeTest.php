<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WelcomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_welcome_page_renders_with_inertia(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/foundation');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->has('laravelVersion')
            ->has('phpVersion')
            ->where('dbStatus', 'Connected')
        );
    }
}
