<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TodayTest extends TestCase
{
    use RefreshDatabase;

    public function test_today_prototype_screen_renders_with_inertia(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Today')
            ->has('tasks')
            ->has('occurrences')
            ->has('todayWorkSessions')
            ->has('recentWorkSessions')
            ->has('todayDate')
            ->has('selectedDate')
            ->has('userTimezone')
        );
    }
}
