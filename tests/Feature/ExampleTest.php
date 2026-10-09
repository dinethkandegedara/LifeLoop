<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example verifying guest login page availability.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }
}
