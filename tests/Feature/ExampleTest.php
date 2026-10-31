<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Route '/' dilindungi auth, jadi pengunjung anonim diarahkan ke login.
     */
    public function test_the_application_redirects_anonymous_users_to_login(): void
    {
        $response = $this->get('/');

        $response->assertStatus(302);
        $response->assertRedirect(route('login'));
    }
}
