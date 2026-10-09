<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WelcomeTest extends TestCase
{
    public function test_welcome_page_renders_with_inertia(): void
    {
        $response = $this->get('/foundation');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->has('laravelVersion')
            ->has('phpVersion')
            ->where('dbStatus', 'Connected')
        );
    }
}
